<?php

namespace App\Services\Finance;

use App\Enums\InvoiceState;
use App\Models\Finance\Contract;
use App\Models\Finance\Invoice;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class InvoiceService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{client_id?: int, state?: string, sort?: string}  $filters
     * @return array{invoices: list<array<string, mixed>>, receivables: list<array<string, mixed>>}
     */
    public function forManagement(array $filters = []): array
    {
        $query = Invoice::query()->with(['client', 'contract'])->orderByDesc('issued_on')->orderByDesc('id');
        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (isset($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        $invoices = [];
        $receivables = [];
        $today = CarbonImmutable::today('Africa/Niamey');
        foreach ($query->get() as $invoice) {
            $row = $this->summary($invoice, $today);
            $invoices[] = $row;
            if ($row['is_overdue']) {
                $receivables[] = $row;
            }
        }

        usort($receivables, fn (array $left, array $right): int => ($filters['sort'] ?? 'age_desc') === 'outstanding_desc'
            ? $right['outstanding_amount'] <=> $left['outstanding_amount']
            : $right['age_days'] <=> $left['age_days']);

        return ['invoices' => $invoices, 'receivables' => $receivables];
    }

    /** @param array{client_id: int, contract_id: int, total_amount: int, issued_on: string, due_on: string} $data */
    public function create(array $data, User $actor): Invoice
    {
        $contract = Contract::query()->findOrFail($data['contract_id']);
        if ((int) $contract->client_id !== $data['client_id']) {
            throw ValidationException::withMessages(['contract_id' => 'Le contrat doit appartenir au client sélectionné.']);
        }

        $invoice = new Invoice([
            'client_id' => $data['client_id'],
            'contract_id' => $data['contract_id'],
            'number' => $this->nextNumber(),
            'total_amount' => $data['total_amount'],
            'issued_on' => $data['issued_on'],
            'due_on' => $data['due_on'],
        ]);
        $invoice->forceFill(['state' => InvoiceState::Impayee->value]);

        return DB::connection($invoice->getConnectionName())->transaction(function () use ($invoice, $actor): Invoice {
            $this->auditLogger->runExplicitly(
                auditable: $invoice,
                operation: fn (): bool => $invoice->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'invoice_created',
                newValues: $invoice->getAttributes(),
                reason: 'Création de la facture.',
            );

            return $invoice->load(['client', 'contract']);
        });
    }

    public function cancel(Invoice $invoice, string $reason, User $actor): Invoice
    {
        return DB::connection($invoice->getConnectionName())->transaction(function () use ($invoice, $reason, $actor): Invoice {
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state === InvoiceState::Annulee) {
                throw ValidationException::withMessages(['reason' => 'Cette facture est déjà annulée.']);
            }
            if ($locked->outstandingAmount() < $locked->total_amount) {
                throw ValidationException::withMessages(['reason' => 'Une facture encaissée doit être corrigée par contre-écriture avant annulation.']);
            }

            $oldValues = ['state' => $locked->state->value, 'cancellation_reason' => $locked->cancellation_reason];
            $locked->forceFill([
                'state' => InvoiceState::Annulee->value,
                'cancellation_reason' => trim($reason),
                'cancelled_at' => CarbonImmutable::now('UTC'),
            ]);
            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: fn (): bool => $locked->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'invoice_cancelled',
                oldValues: $oldValues,
                newValues: [
                    'state' => InvoiceState::Annulee->value,
                    'cancellation_reason' => trim($reason),
                    'cancelled_at' => $locked->getAttribute('cancelled_at'),
                ],
                reason: trim($reason),
            );

            return $locked;
        });
    }

    public function refreshDerivedState(Invoice $invoice): Invoice
    {
        $state = $invoice->derivedState();
        if ($invoice->state !== $state) {
            $invoice->forceFill(['state' => $state->value])->saveOrFail();
        }

        return $invoice;
    }

    /** @return array<string, mixed> */
    private function summary(Invoice $invoice, CarbonImmutable $today): array
    {
        $outstanding = $invoice->outstandingAmount();
        $isOverdue = $invoice->state !== InvoiceState::Annulee
            && $outstanding > 0
            && $invoice->due_on->lessThanOrEqualTo($today);
        $dueDate = CarbonImmutable::parse($invoice->due_on->toDateString(), 'Africa/Niamey')->startOfDay();
        $ageDays = $isOverdue ? (int) $dueDate->diffInDays($today->startOfDay()) : 0;

        return [
            'id' => (int) $invoice->getKey(),
            'number' => $invoice->number,
            'client_id' => (int) $invoice->client_id,
            'client_name' => $invoice->client->name,
            'contract_id' => (int) $invoice->contract_id,
            'contract_reference' => $invoice->contract->reference,
            'total_amount' => $invoice->total_amount,
            'total_amount_label' => Money::from($invoice->total_amount)->format(),
            'outstanding_amount' => $outstanding,
            'outstanding_amount_label' => Money::from($outstanding)->format(),
            'issued_on' => $invoice->issued_on->toDateString(),
            'due_on' => $invoice->due_on->toDateString(),
            'state' => $invoice->state->value,
            'is_overdue' => $isOverdue,
            'age_days' => $ageDays,
            'age_label' => $ageDays.' jour'.($ageDays > 1 ? 's' : ''),
            'cancellation_reason' => $invoice->cancellation_reason,
        ];
    }

    private function nextNumber(): string
    {
        return 'FAC-'.now('Africa/Niamey')->format('Ym').'-'.Str::upper(Str::substr((string) Str::ulid(), -10));
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
