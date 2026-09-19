<?php

namespace App\Services\Finance;

use App\Enums\FinancialMovementDirection;
use App\Enums\ReconciliationState;
use App\Models\Finance\Account;
use App\Models\Finance\Reconciliation;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReconciliationService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @return list<array<string, mixed>> */
    public function listing(User $viewer): array
    {
        return Reconciliation::query()->visibleTo($viewer)->with(['account', 'preparer.person', 'controller.person', 'previous'])
            ->orderByDesc('period_end')->get()->map(fn (Reconciliation $item): array => [
                'id' => (int) $item->getKey(), 'account' => $item->account->label,
                'period_start' => $item->period_start->toDateString(), 'period_end' => $item->period_end->toDateString(),
                'calculated_balance_label' => Money::from($item->calculated_balance_amount)->format(),
                'physical_balance_label' => Money::from($item->physical_balance_amount)->format(),
                'difference_amount' => $item->difference_amount,
                'difference_label' => ($item->difference_direction === FinancialMovementDirection::Debit ? '−' : ($item->difference_amount > 0 ? '+' : '')).Money::from($item->difference_amount)->format(),
                'difference_explanation' => $item->difference_explanation, 'corrective_action' => $item->corrective_action,
                'state' => $item->state->value, 'prepared_by' => $item->prepared_by, 'controlled_by' => $item->controlled_by,
                'previous_id' => $item->previous_id, 'correction_reason' => $item->correction_reason,
            ])->all();
    }

    /** @param array<string, mixed> $data */
    public function prepare(array $data, User $actor, ?Reconciliation $previous = null): Reconciliation
    {
        return DB::transaction(function () use ($data, $actor, $previous): Reconciliation {
            $account = Account::query()->whereKey((int) $data['account_id'])->lockForUpdate()->firstOrFail();
            $end = CarbonImmutable::parse((string) $data['period_end'], 'Africa/Niamey')->toDateString();
            $calculated = $account->balanceAt($end);
            if ($calculated < 0) {
                throw ValidationException::withMessages(['account_id' => 'Le rapprochement ne peut pas figer un solde calculé négatif dans le modèle XOF non signé.']);
            }
            $physical = (int) $data['physical_balance_amount'];
            $signedDifference = $physical - $calculated;
            $item = new Reconciliation([
                'account_id' => $account->getKey(), 'period_start' => (string) $data['period_start'], 'period_end' => $end,
                'calculated_balance_amount' => $calculated, 'physical_balance_amount' => $physical,
                'difference_direction' => $signedDifference === 0 ? null : ($signedDifference > 0 ? FinancialMovementDirection::Credit->value : FinancialMovementDirection::Debit->value),
                'difference_amount' => abs($signedDifference), 'difference_explanation' => $this->text($data['difference_explanation'] ?? null),
                'responsible_id' => $data['responsible_id'] ?? null, 'corrective_action' => $this->text($data['corrective_action'] ?? null),
                'prepared_by' => $actor->getKey(), 'state' => ReconciliationState::Draft->value,
                'previous_id' => $previous?->getKey(), 'correction_reason' => $this->text($data['correction_reason'] ?? null),
            ]);
            if ($previous !== null && $item->correction_reason === null) {
                throw ValidationException::withMessages(['correction_reason' => 'Le motif de correction est obligatoire.']);
            }
            $this->auditLogger->runExplicitly(
                auditable: $item, operation: fn (): bool => $item->saveOrFail(), actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor), action: $previous === null ? 'reconciliation_prepared' : 'reconciliation_corrected',
                newValues: $item->getAttributes(), reason: $item->correction_reason ?? 'Préparation du rapprochement hebdomadaire.',
            );

            return $item;
        });
    }

    public function validate(Reconciliation $reconciliation, User $actor): Reconciliation
    {
        return DB::transaction(function () use ($reconciliation, $actor): Reconciliation {
            $locked = Reconciliation::query()->whereKey($reconciliation->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state !== ReconciliationState::Draft) {
                throw ValidationException::withMessages(['reconciliation' => 'Un rapprochement validé est immuable.']);
            }
            if ((int) $locked->prepared_by === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['controlled_by' => 'Le préparateur et le contrôleur doivent être deux comptes distincts.']);
            }
            if ($locked->difference_amount > 0 && ($locked->difference_explanation === null || $locked->responsible_id === null || $locked->corrective_action === null)) {
                throw ValidationException::withMessages(['difference' => 'Un écart non nul exige une explication, un responsable et une action corrective.']);
            }
            $oldValues = $locked->getRawOriginal();
            $locked->forceFill(['controlled_by' => $actor->getKey(), 'state' => ReconciliationState::Validated->value, 'validated_at' => CarbonImmutable::now('UTC')]);
            $this->auditLogger->runExplicitly(
                auditable: $locked, operation: fn (): bool => $locked->saveOrFail(), actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor), action: 'reconciliation_validated', oldValues: $oldValues,
                newValues: $locked->getAttributes(), reason: 'Validation par un contrôleur distinct du préparateur #'.$locked->prepared_by.'.',
            );

            return $locked;
        });
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
