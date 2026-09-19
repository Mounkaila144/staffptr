<?php

namespace App\Services\Finance;

use App\Enums\ExpenseState;
use App\Enums\FinancialAccountState;
use App\Enums\FinancialMovementDirection;
use App\Models\Finance\Account;
use App\Models\Finance\AccountMovement;
use App\Models\Finance\Expense;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Platform\AttachmentService;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ExpensePaymentService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private AttachmentService $attachments,
        private MonthGuard $monthGuard,
    ) {}

    /** @return array{expenses: list<array<string, mixed>>, missing_attachments: list<array<string, mixed>>} */
    public function forManagement(): array
    {
        $rows = Expense::query()
            ->whereIn('state', [ExpenseState::Approuvee->value, ExpenseState::Payee->value, ExpenseState::Annulee->value])
            ->with(['requester.person', 'category', 'account', 'contract', 'project', 'paymentAttachment'])
            ->orderByDesc('created_at')->get()
            ->map(fn (Expense $expense): array => [
                'id' => (int) $expense->getKey(),
                'reason' => $expense->reason,
                'beneficiary' => $expense->beneficiary,
                'requested_amount' => $expense->requested_amount,
                'requested_amount_label' => Money::from($expense->requested_amount)->format(),
                'state' => $expense->state->value,
                'account_label' => $expense->account?->label,
                'paid_at' => $expense->paid_at?->setTimezone('Africa/Niamey')->format('Y-m-d H:i'),
                'contract_reference' => $expense->contract?->reference,
                'project_name' => $expense->project?->name,
                'has_payment_attachment' => $expense->payment_attachment_id !== null,
                'is_advance_reimbursement' => (bool) $expense->is_advance_reimbursement,
                'counter_entry_of_id' => $expense->counter_entry_of_id,
            ])->all();

        return [
            'expenses' => $rows,
            'missing_attachments' => array_values(array_filter($rows, fn (array $row): bool => $row['state'] === ExpenseState::Payee->value && ! $row['has_payment_attachment'] && $row['counter_entry_of_id'] === null)),
        ];
    }

    /**
     * @param  array{account_id: int, paid_at: string, payment_mode: string, payment_reference?: string|null, project_id?: int|null, contract_id?: int|null, attachment_ulid?: string|null, is_advance_reimbursement: bool, payment_idempotency_key: string}  $data
     */
    public function pay(Expense $expense, array $data, User $actor): Expense
    {
        $existing = Expense::query()->where('payment_idempotency_key', $data['payment_idempotency_key'])->first();
        if ($existing !== null) {
            if ((int) $existing->paid_by !== (int) $actor->getKey()) {
                throw ValidationException::withMessages(['payment_idempotency_key' => 'Cette clé de paiement est déjà utilisée.']);
            }

            return $existing;
        }

        return DB::transaction(function () use ($expense, $data, $actor): Expense {
            $account = Account::query()->whereKey($data['account_id'])->lockForUpdate()->firstOrFail();
            $entitlement = $expense->share_entitlement_id === null ? null : ShareEntitlement::query()->whereKey($expense->share_entitlement_id)->lockForUpdate()->firstOrFail();
            $locked = Expense::query()->with(['attachment', 'approvals'])->whereKey($expense->getKey())->lockForUpdate()->firstOrFail();
            $paidAt = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $data['paid_at'], 'Africa/Niamey')->utc();
            $this->monthGuard->assertOpen($paidAt);
            $this->assertPayable($locked, $account, $data);
            if ($entitlement !== null && $locked->requested_amount > $entitlement->remainingAmount()) {
                throw ValidationException::withMessages(['state' => 'Le versement dépasse le montant restant de la part.']);
            }
            $paymentAttachment = $this->stagedAttachment($data['attachment_ulid'] ?? null, $actor);

            $locked->forceFill([
                'state' => ExpenseState::Payee->value,
                'account_id' => $account->getKey(),
                'paid_on' => $paidAt->setTimezone('Africa/Niamey')->toDateString(),
                'payment_mode' => $data['payment_mode'],
                'payment_reference' => $this->nullableText($data['payment_reference'] ?? null),
                'paid_by' => $actor->getKey(),
                'paid_at' => $paidAt,
                'project_id' => $data['project_id'] ?? null,
                'contract_id' => $data['contract_id'] ?? null,
                'payment_attachment_id' => $paymentAttachment?->getKey(),
                'original_attachment_id' => (bool) $data['is_advance_reimbursement'] ? $locked->attachment?->getKey() : null,
                'is_advance_reimbursement' => (bool) $data['is_advance_reimbursement'],
                'post_reopening' => $this->monthGuard->recordedAfterReopen($paidAt),
                'payment_idempotency_key' => $data['payment_idempotency_key'],
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: function () use ($locked, $account, $actor, $paymentAttachment, $entitlement): bool {
                    $locked->saveOrFail();
                    $this->movement($locked, $account, FinancialMovementDirection::Debit, $actor);
                    if ($entitlement !== null) {
                        $entitlement->forceFill(['paid_amount' => $entitlement->paid_amount + $locked->requested_amount])->saveOrFail();
                    }
                    if ($paymentAttachment !== null) {
                        $this->attachments->attachExisting($paymentAttachment, $locked, $actor);
                    }

                    return true;
                },
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'expense_paid',
                newValues: $locked->getAttributes(),
                reason: 'Paiement d’une dépense approuvée.',
            );

            return $locked->load(['account', 'contract', 'project', 'paymentAttachment']);
        });
    }

    public function cancel(Expense $expense, string $reason, string $idempotencyKey, User $actor): Expense
    {
        $existing = Expense::query()->where('payment_idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($expense, $reason, $idempotencyKey, $actor): Expense {
            $account = Account::query()->whereKey($expense->account_id)->lockForUpdate()->firstOrFail();
            $entitlement = $expense->share_entitlement_id === null ? null : ShareEntitlement::query()->whereKey($expense->share_entitlement_id)->lockForUpdate()->firstOrFail();
            $locked = Expense::query()->whereKey($expense->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state !== ExpenseState::Payee || $locked->counter_entry_of_id !== null || $locked->counterEntries()->exists()) {
                throw ValidationException::withMessages(['reason' => 'Seule une dépense payée courante peut être annulée.']);
            }
            $now = CarbonImmutable::now('UTC');
            $this->monthGuard->assertOpen($now);
            $counter = new Expense([
                'requester_id' => $locked->requester_id,
                'category_id' => $locked->category_id,
                'reason' => 'Contre-écriture : '.$locked->reason,
                'requested_amount' => $locked->requested_amount,
                'beneficiary' => $locked->beneficiary,
                'expected_result' => 'Annulation comptable du paiement #'.$locked->getKey(),
                'project_or_contract_note' => $locked->project_or_contract_note,
                'account_id' => $account->getKey(),
                'paid_on' => $now->setTimezone('Africa/Niamey')->toDateString(),
                'payment_mode' => $locked->payment_mode,
                'payment_reference' => 'Annulation dépense #'.$locked->getKey(),
                'paid_by' => $actor->getKey(),
                'paid_at' => $now,
                'project_id' => $locked->project_id,
                'contract_id' => $locked->contract_id,
                'beneficiary_user_id' => $locked->beneficiary_user_id,
                'share_entitlement_id' => $locked->share_entitlement_id,
                'counter_entry_of_id' => $locked->getKey(),
                'post_reopening' => $this->monthGuard->recordedAfterReopen($now),
                'payment_idempotency_key' => $idempotencyKey,
                'payment_cancellation_reason' => trim($reason),
            ]);
            $counter->forceFill(['state' => ExpenseState::Payee->value]);

            $this->auditLogger->runExplicitly(
                auditable: $counter,
                operation: function () use ($counter, $locked, $account, $actor, $reason, $entitlement): bool {
                    $counter->saveOrFail();
                    $locked->forceFill([
                        'state' => ExpenseState::Annulee->value,
                        'payment_cancellation_reason' => trim($reason),
                    ])->saveOrFail();
                    $this->movement($counter, $account, FinancialMovementDirection::Credit, $actor, $locked);
                    if ($entitlement !== null) {
                        $entitlement->forceFill(['paid_amount' => max(0, $entitlement->paid_amount - $locked->requested_amount)])->saveOrFail();
                    }

                    return true;
                },
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'expense_payment_cancelled_by_counter_entry',
                newValues: $counter->getAttributes(),
                reason: trim($reason),
            );

            return $counter;
        });
    }

    /** @param array<string, mixed> $data */
    private function assertPayable(Expense $expense, Account $account, array $data): void
    {
        if (! $expense->isPayable()) {
            throw ValidationException::withMessages(['state' => 'Seule une dépense approuvée est payable.']);
        }
        if ($expense->approvals()->where('decision', 'approve')->distinct('approver_id')->count('approver_id') !== 2) {
            throw ValidationException::withMessages(['state' => 'Le paiement exige deux approbations de direction distinctes.']);
        }
        if ($account->state !== FinancialAccountState::Active) {
            throw ValidationException::withMessages(['account_id' => 'Le compte débité doit être actif.']);
        }
        if (isset($data['contract_id']) && isset($data['project_id'])) {
            throw ValidationException::withMessages(['contract_id' => 'Imputez la dépense à un contrat ou à un projet, pas aux deux.']);
        }
        if ((bool) $data['is_advance_reimbursement'] && $expense->attachment === null) {
            throw ValidationException::withMessages(['is_advance_reimbursement' => 'Le remboursement d’avance exige le justificatif d’origine.']);
        }
    }

    private function movement(Expense $expense, Account $account, FinancialMovementDirection $direction, User $actor, ?Expense $reversed = null): AccountMovement
    {
        $reversalMovement = $reversed === null ? null : AccountMovement::query()
            ->where('source_type', $reversed->getMorphClass())->where('source_id', $reversed->getKey())
            ->where('direction', FinancialMovementDirection::Debit->value)->first();

        return AccountMovement::query()->create([
            'account_id' => $account->getKey(),
            'direction' => $direction->value,
            'movement_amount' => $expense->requested_amount,
            'effective_on' => $expense->paid_on->toDateString(),
            'source_type' => $expense->getMorphClass(),
            'source_id' => $expense->getKey(),
            'description' => ($direction === FinancialMovementDirection::Debit ? 'Paiement ' : 'Contre-écriture ').'dépense #'.$expense->getKey(),
            'reversal_of_id' => $reversalMovement?->getKey(),
            'created_by' => $actor->getKey(),
            'validated_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    private function stagedAttachment(?string $ulid, User $actor): ?Attachment
    {
        if ($ulid === null || $ulid === '') {
            return null;
        }
        $attachment = Attachment::query()->where('ulid', $ulid)->lockForUpdate()->firstOrFail();
        if ($attachment->getAttribute('attachable_type') !== $actor->person->getMorphClass()
            || (int) $attachment->getAttribute('attachable_id') !== (int) $actor->person_id
            || (int) $attachment->getAttribute('uploaded_by') !== (int) $actor->getKey()) {
            throw ValidationException::withMessages(['attachment_ulid' => 'Ce justificatif ne peut pas être rattaché à ce paiement.']);
        }

        return $attachment;
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
