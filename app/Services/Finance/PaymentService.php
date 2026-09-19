<?php

namespace App\Services\Finance;

use App\Enums\FinancialAccountState;
use App\Enums\FinancialMovementDirection;
use App\Enums\InvoiceState;
use App\Enums\PaymentState;
use App\Models\Finance\Account;
use App\Models\Finance\AccountMovement;
use App\Models\Finance\Contract;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Platform\AttachmentService;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PaymentService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private AttachmentService $attachmentService,
        private MonthGuard $monthGuard,
        private InvoiceService $invoices,
        private ShareEntitlementService $shares,
        private ReserveService $reserve,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forManagement(): array
    {
        return Payment::query()->with(['client', 'contract', 'project', 'invoice', 'account', 'attachment'])
            ->orderByDesc('received_at')->orderByDesc('id')->get()
            ->map(fn (Payment $payment): array => [
                'id' => (int) $payment->getKey(),
                'receipt_number' => $payment->receipt_number,
                'client_id' => (int) $payment->client_id,
                'contract_id' => $payment->contract_id === null ? null : (int) $payment->contract_id,
                'project_id' => $payment->project_id === null ? null : (int) $payment->project_id,
                'invoice_id' => $payment->invoice_id === null ? null : (int) $payment->invoice_id,
                'account_id' => (int) $payment->account_id,
                'client_name' => $payment->client->name,
                'contract_reference' => $payment->contract?->reference,
                'project_name' => $payment->project?->name,
                'invoice_number' => $payment->invoice?->number,
                'account_label' => $payment->account->label,
                'received_amount' => $payment->received_amount,
                'received_amount_label' => Money::from($payment->received_amount)->format(),
                'received_at' => $payment->received_at?->setTimezone('Africa/Niamey')->format('Y-m-d H:i'),
                'payment_mode' => $payment->payment_mode->value,
                'reference' => $payment->reference,
                'state' => $payment->state->value,
                'late_recording' => $payment->late_recording,
                'has_attachment' => $payment->attachment !== null,
                'correction_of_id' => $payment->correction_of_id,
                'reversal_of_id' => $payment->reversal_of_id,
            ])->all();
    }

    /**
     * @param  array{client_id: int, contract_id?: int|null, project_id?: int|null, invoice_id?: int|null, account_id: int, received_amount: int, received_at: string, payment_mode: string, reference?: string|null, attachment_ulid?: string|null, idempotency_key: string}  $data
     */
    public function record(array $data, User $actor): Payment
    {
        $existing = $this->idempotentPayment($data['idempotency_key'], $actor);
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($data, $actor): Payment {
            $account = Account::query()->whereKey($data['account_id'])->lockForUpdate()->firstOrFail();
            $contract = isset($data['contract_id']) ? Contract::query()->whereKey($data['contract_id'])->lockForUpdate()->firstOrFail() : null;
            $invoice = isset($data['invoice_id']) ? Invoice::query()->whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail() : null;
            $receivedAt = $this->receivedAt($data['received_at']);
            $this->monthGuard->assertOpen($receivedAt);
            $this->assertCoherent($data, $account, $contract, $invoice);
            $attachment = $this->lockedStagedAttachment($data['attachment_ulid'] ?? null, $actor);

            return $this->createPayment(
                data: $data,
                actor: $actor,
                account: $account,
                contract: $contract,
                invoice: $invoice,
                receivedAt: $receivedAt,
                attachment: $attachment,
            );
        });
    }

    /**
     * @param  array{client_id: int, contract_id?: int|null, project_id?: int|null, invoice_id?: int|null, account_id: int, received_amount: int, received_at: string, payment_mode: string, reference?: string|null, attachment_ulid?: string|null, idempotency_key: string, correction_reason: string}  $data
     */
    public function correct(Payment $original, array $data, User $actor): Payment
    {
        $existing = $this->idempotentPayment($data['idempotency_key'], $actor);
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($original, $data, $actor): Payment {
            $accountIds = array_values(array_unique([(int) $original->account_id, $data['account_id']]));
            sort($accountIds);
            $accounts = Account::query()->whereKey($accountIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $oldAccount = $accounts->get((int) $original->account_id) ?? throw ValidationException::withMessages(['payment' => 'Le compte d’origine est introuvable.']);
            $newAccount = $accounts->get($data['account_id']) ?? throw ValidationException::withMessages(['account_id' => 'Le compte corrigé est introuvable.']);
            $contract = isset($data['contract_id']) ? Contract::query()->whereKey($data['contract_id'])->lockForUpdate()->firstOrFail() : null;
            $invoice = isset($data['invoice_id']) ? Invoice::query()->whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail() : null;
            $locked = Payment::query()->whereKey($original->getKey())->lockForUpdate()->firstOrFail();
            $receivedAt = $this->receivedAt($data['received_at']);
            $this->monthGuard->assertOpen($receivedAt);
            $this->assertCorrectable($locked);
            $locked->forceFill(['state' => PaymentState::Corrected->value, 'correction_reason' => trim($data['correction_reason'])])->saveOrFail();
            $this->assertCoherent($data, $newAccount, $contract, $invoice);
            $attachment = $this->lockedStagedAttachment($data['attachment_ulid'] ?? null, $actor);

            $corrected = $this->createPayment($data, $actor, $newAccount, $contract, $invoice, $receivedAt, $attachment, $locked, $oldAccount);

            return $corrected;
        });
    }

    public function cancel(Payment $original, string $reason, string $idempotencyKey, User $actor): Payment
    {
        $existing = $this->idempotentPayment($idempotencyKey, $actor);
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($original, $reason, $idempotencyKey, $actor): Payment {
            $account = Account::query()->whereKey($original->account_id)->lockForUpdate()->firstOrFail();
            $contract = $original->contract_id === null ? null : Contract::query()->whereKey($original->contract_id)->lockForUpdate()->firstOrFail();
            $invoice = $original->invoice_id === null ? null : Invoice::query()->whereKey($original->invoice_id)->lockForUpdate()->firstOrFail();
            $locked = Payment::query()->whereKey($original->getKey())->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now('UTC');
            $this->monthGuard->assertOpen($now);
            $this->assertCorrectable($locked);
            $sequence = $this->consumeReceiptSequence();
            $reversal = new Payment([
                'receipt_sequence_id' => $sequence,
                'receipt_number' => $this->receiptNumber($sequence),
                'client_id' => $locked->client_id,
                'contract_id' => $locked->contract_id,
                'project_id' => $locked->project_id,
                'invoice_id' => $locked->invoice_id,
                'account_id' => $account->getKey(),
                'received_amount' => $locked->received_amount,
                'received_on' => $now->setTimezone('Africa/Niamey')->toDateString(),
                'received_at' => $now,
                'payment_mode' => $locked->payment_mode->value,
                'reference' => 'Annulation '.$locked->receipt_number,
                'state' => PaymentState::Validated->value,
                'reversal_of_id' => $locked->getKey(),
                'cancellation_reason' => trim($reason),
                'late_recording' => false,
                'post_reopening' => $this->monthGuard->recordedAfterReopen($now),
                'idempotency_key' => $idempotencyKey,
                'created_by' => $actor->getKey(),
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $reversal,
                operation: function () use ($reversal, $locked, $account, $actor, $invoice): bool {
                    $reversal->saveOrFail();
                    $locked->forceFill(['state' => PaymentState::Cancelled->value, 'cancellation_reason' => $reversal->cancellation_reason])->saveOrFail();
                    $this->movement($reversal, $account, FinancialMovementDirection::Debit, $actor, $locked);
                    $this->shares->reverseForPayment($locked, $reversal);
                    $this->reserve->reverseForPayment($locked, $reversal, $actor);
                    if ($invoice !== null) {
                        $this->invoices->refreshDerivedState($invoice);
                    }

                    return true;
                },
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'payment_cancelled_by_counter_entry',
                newValues: $reversal->getAttributes(),
                reason: trim($reason),
            );

            return $reversal;
        });
    }

    /** @param array<string, mixed> $data */
    private function createPayment(
        array $data,
        User $actor,
        Account $account,
        ?Contract $contract,
        ?Invoice $invoice,
        CarbonImmutable $receivedAt,
        ?Attachment $attachment,
        ?Payment $correctionOf = null,
        ?Account $oldAccount = null,
    ): Payment {
        $sequence = $this->consumeReceiptSequence();
        $payment = new Payment([
            'receipt_sequence_id' => $sequence,
            'receipt_number' => $this->receiptNumber($sequence),
            'client_id' => $data['client_id'],
            'contract_id' => $contract?->getKey(),
            'project_id' => $data['project_id'] ?? null,
            'invoice_id' => $invoice?->getKey(),
            'account_id' => $account->getKey(),
            'received_amount' => $data['received_amount'],
            'received_on' => $receivedAt->setTimezone('Africa/Niamey')->toDateString(),
            'received_at' => $receivedAt,
            'payment_mode' => $data['payment_mode'],
            'reference' => $this->nullableText($data['reference'] ?? null),
            'state' => PaymentState::Validated->value,
            'correction_of_id' => $correctionOf?->getKey(),
            'correction_reason' => $correctionOf === null ? null : trim((string) $data['correction_reason']),
            'late_recording' => $receivedAt->diffInHours(CarbonImmutable::now('UTC'), false) > 24,
            'post_reopening' => $this->monthGuard->recordedAfterReopen($receivedAt),
            'idempotency_key' => $data['idempotency_key'],
            'created_by' => $actor->getKey(),
        ]);

        $this->auditLogger->runExplicitly(
            auditable: $payment,
            operation: function () use ($payment, $account, $contract, $invoice, $attachment, $actor, $correctionOf, $oldAccount): bool {
                $payment->saveOrFail();
                if ($correctionOf !== null && $oldAccount !== null) {
                    $this->movement($payment, $oldAccount, FinancialMovementDirection::Debit, $actor, $correctionOf);
                    $this->shares->reverseForPayment($correctionOf, $payment);
                    $this->reserve->reverseForPayment($correctionOf, $payment, $actor);
                }
                $this->movement($payment, $account, FinancialMovementDirection::Credit, $actor);
                if ($contract !== null) {
                    $this->shares->createForPayment($payment, $contract);
                    $this->reserve->allocateFrom($payment, $contract, $actor);
                }
                if ($invoice !== null) {
                    $this->invoices->refreshDerivedState($invoice);
                }
                if ($attachment !== null) {
                    $this->attachmentService->attachExisting($attachment, $payment, $actor);
                }

                return true;
            },
            actorId: (int) $actor->getKey(),
            actorLabel: $this->actorLabel($actor),
            action: $correctionOf === null ? 'payment_recorded' : 'payment_corrected',
            newValues: [...$payment->getAttributes(), 'attachment_ulid' => $attachment?->ulid],
            reason: $correctionOf === null ? 'Encaissement validé.' : trim((string) $data['correction_reason']),
        );

        return $payment->load(['client', 'contract', 'project', 'invoice', 'account', 'attachment', 'shareEntitlements']);
    }

    /** @param array<string, mixed> $data */
    private function assertCoherent(array $data, Account $account, ?Contract $contract, ?Invoice $invoice): void
    {
        if ($account->state !== FinancialAccountState::Active) {
            throw ValidationException::withMessages(['account_id' => 'Le compte crédité doit être actif.']);
        }
        if ($contract === null && ! isset($data['project_id'])) {
            throw ValidationException::withMessages(['contract_id' => 'Imputez l’encaissement à un contrat ou à un projet.']);
        }
        if ($contract !== null && (int) $contract->client_id !== (int) $data['client_id']) {
            throw ValidationException::withMessages(['contract_id' => 'Le contrat n’appartient pas au client sélectionné.']);
        }
        if ($invoice !== null) {
            if ($invoice->state === InvoiceState::Annulee || (int) $invoice->client_id !== (int) $data['client_id'] || (int) $invoice->contract_id !== (int) $contract?->getKey()) {
                throw ValidationException::withMessages(['invoice_id' => 'La facture ne correspond pas au client et au contrat sélectionnés.']);
            }
            if ((int) $data['received_amount'] > $invoice->outstandingAmount()) {
                throw ValidationException::withMessages(['received_amount' => 'Le montant dépasse le reste dû de la facture.']);
            }
        }
    }

    private function assertCorrectable(Payment $payment): void
    {
        if ($payment->state !== PaymentState::Validated || $payment->reversal_of_id !== null) {
            throw ValidationException::withMessages(['payment' => 'Seul un encaissement validé courant peut être corrigé ou annulé.']);
        }
    }

    private function movement(Payment $payment, Account $account, FinancialMovementDirection $direction, User $actor, ?Payment $reversed = null): AccountMovement
    {
        $reversalMovement = $reversed === null ? null : AccountMovement::query()
            ->where('source_type', $reversed->getMorphClass())->where('source_id', $reversed->getKey())
            ->where('direction', FinancialMovementDirection::Credit->value)->first();

        return AccountMovement::query()->create([
            'account_id' => $account->getKey(),
            'direction' => $direction->value,
            'movement_amount' => $reversed === null ? $payment->received_amount : $reversed->received_amount,
            'effective_on' => $payment->received_on->toDateString(),
            'source_type' => $payment->getMorphClass(),
            'source_id' => $payment->getKey(),
            'description' => ($direction === FinancialMovementDirection::Credit ? 'Encaissement ' : 'Contre-écriture ').$payment->receipt_number,
            'reversal_of_id' => $reversalMovement?->getKey(),
            'created_by' => $actor->getKey(),
            'validated_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    private function lockedStagedAttachment(?string $ulid, User $actor): ?Attachment
    {
        if ($ulid === null || $ulid === '') {
            return null;
        }
        $attachment = Attachment::query()->where('ulid', $ulid)->lockForUpdate()->firstOrFail();
        if ($attachment->getAttribute('attachable_type') !== $actor->person->getMorphClass()
            || (int) $attachment->getAttribute('attachable_id') !== (int) $actor->person_id
            || (int) $attachment->getAttribute('uploaded_by') !== (int) $actor->getKey()) {
            throw ValidationException::withMessages(['attachment_ulid' => 'Ce justificatif privé ne peut pas être rattaché à cet encaissement.']);
        }

        return $attachment;
    }

    private function idempotentPayment(string $key, User $actor): ?Payment
    {
        $payment = Payment::query()->where('idempotency_key', $key)->first();
        if ($payment !== null && (int) $payment->created_by !== (int) $actor->getKey()) {
            throw ValidationException::withMessages(['idempotency_key' => 'Cette clé d’envoi est déjà utilisée.']);
        }

        return $payment;
    }

    private function consumeReceiptSequence(): int
    {
        return (int) DB::table('receipt_sequences')->insertGetId(['consumed_at' => CarbonImmutable::now('UTC')]);
    }

    private function receiptNumber(int $sequence): string
    {
        return sprintf('REC-%010d', $sequence);
    }

    private function receivedAt(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $value, 'Africa/Niamey')->utc();
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
