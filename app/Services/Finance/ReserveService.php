<?php

namespace App\Services\Finance;

use App\Enums\ReserveMovementType;
use App\Models\Finance\Contract;
use App\Models\Finance\Payment;
use App\Models\Finance\ReserveMovement;
use App\Models\Identity\User;
use App\Services\Platform\SettingsService;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class ReserveService
{
    public function __construct(
        private FixedChargeService $fixedCharges,
        private SettingsService $settings,
        private AuditLogger $auditLogger,
    ) {}

    public function currentAmount(): int
    {
        $credits = (int) ReserveMovement::query()
            ->where('approval_state', 'approved')
            ->whereIn('type', [ReserveMovementType::Allocation->value, ReserveMovementType::Reconstitution->value])
            ->sum('movement_amount');
        $debits = (int) ReserveMovement::query()
            ->where('approval_state', 'approved')
            ->whereIn('type', [ReserveMovementType::Usage->value, ReserveMovementType::Reversal->value])
            ->sum('movement_amount');

        return max(0, $credits - $debits);
    }

    /** @return array<string, mixed> */
    public function summary(?User $viewer = null): array
    {
        $amount = $this->currentAmount();
        $monthlyBase = $this->fixedCharges->currentBaseAmount();
        $objective = $this->fixedCharges->reserveObjectiveAmount();
        $coveredBasisPoints = $monthlyBase > 0 ? intdiv($amount * 10_000, $monthlyBase) : 0;
        $sourceDate = ReserveMovement::query()->max('occurred_on') ?? now('Africa/Niamey')->toDateString();

        return [
            'amount' => $amount,
            'amount_label' => Money::from($amount)->format(),
            'objective_amount' => $objective,
            'objective_amount_label' => Money::from($objective)->format(),
            'covered_months' => round($coveredBasisPoints / 10_000, 2),
            'target_months' => $this->settings->reserveTargetMonths(),
            'method' => 'Objectif = nombre de mois paramétré × charges fixes actives. Allocation = 20 % du bénéfice encaissé, prélevée uniquement sur les 60 % de PTR Niger, jusqu’à l’objectif.',
            'source_date' => CarbonImmutable::parse($sourceDate)->toDateString(),
            'movements' => ReserveMovement::query()->when($viewer !== null, fn ($query) => $query->visibleTo($viewer))->with(['firstApprover.person', 'secondApprover.person'])
                ->orderByDesc('created_at')->get()->map(fn (ReserveMovement $movement): array => [
                    'id' => (int) $movement->getKey(),
                    'type' => $movement->type->value,
                    'approval_state' => $movement->approval_state,
                    'amount_label' => Money::from($movement->movement_amount)->format(),
                    'occurred_on' => $movement->occurred_on->toDateString(),
                    'reason' => $movement->reason,
                    'reconstitution_plan' => $movement->reconstitution_plan,
                    'first_approved_by' => $movement->first_approved_by,
                    'second_approved_by' => $movement->second_approved_by,
                ])->all(),
        ];
    }

    public function allocateFrom(Payment $payment, Contract $contract, User $actor): ?ReserveMovement
    {
        if ($payment->getConnection()->transactionLevel() < 1) {
            throw new RuntimeException('L’allocation de réserve doit appartenir à la transaction d’encaissement.');
        }
        if (! $contract->has_execution || $contract->contributor_id === null) {
            return null;
        }

        $base = (int) $payment->shareEntitlements()->value('base_amount');
        $proposed = Money::from($base)->allocateByBasisPoints(['reserve' => 2_000, 'remainder' => 8_000])['reserve'];
        $remainingObjective = max(0, $this->fixedCharges->reserveObjectiveAmount() - $this->currentAmount());
        $amount = min($proposed, $remainingObjective);
        if ($amount === 0) {
            return null;
        }

        return ReserveMovement::query()->create([
            'type' => ReserveMovementType::Allocation->value,
            'approval_state' => 'approved',
            'movement_amount' => $amount,
            'payment_id' => $payment->getKey(),
            'occurred_on' => $payment->received_on->toDateString(),
            'reason' => '20 % du bénéfice correspondant, prélevés sur la part PTR Niger de 60 %.',
            'created_by' => $actor->getKey(),
            'idempotency_key' => (string) Str::ulid(),
            'approved_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function reverseForPayment(Payment $original, Payment $reversal, User $actor): ?ReserveMovement
    {
        $allocation = ReserveMovement::query()
            ->where('payment_id', $original->getKey())
            ->where('type', ReserveMovementType::Allocation->value)
            ->lockForUpdate()->first();
        if ($allocation === null) {
            return null;
        }

        return ReserveMovement::query()->create([
            'type' => ReserveMovementType::Reversal->value,
            'approval_state' => 'approved',
            'movement_amount' => $allocation->movement_amount,
            'payment_id' => $reversal->getKey(),
            'reversal_of_id' => $allocation->getKey(),
            'occurred_on' => $reversal->received_on->toDateString(),
            'reason' => 'Contre-écriture de l’allocation liée au reçu '.$original->receipt_number.'.',
            'created_by' => $actor->getKey(),
            'idempotency_key' => (string) Str::ulid(),
            'approved_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function requestUsage(int $amount, string $reason, string $plan, string $idempotencyKey, User $actor): ReserveMovement
    {
        return DB::transaction(function () use ($amount, $reason, $plan, $idempotencyKey, $actor): ReserveMovement {
            $existing = ReserveMovement::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($amount <= 0 || trim($reason) === '' || trim($plan) === '') {
                throw ValidationException::withMessages(['reserve' => 'Montant, motif et plan de reconstitution sont obligatoires.']);
            }
            $movement = new ReserveMovement([
                'type' => ReserveMovementType::Usage->value,
                'approval_state' => 'pending',
                'movement_amount' => $amount,
                'occurred_on' => now('Africa/Niamey')->toDateString(),
                'reason' => trim($reason),
                'reconstitution_plan' => trim($plan),
                'created_by' => $actor->getKey(),
                'idempotency_key' => $idempotencyKey,
                'requested_at' => CarbonImmutable::now('UTC'),
            ]);
            $this->auditLogger->runExplicitly(
                auditable: $movement,
                operation: fn (): bool => $movement->saveOrFail(),
                actorId: (int) $actor->getKey(), actorLabel: $this->actorLabel($actor),
                action: 'reserve_usage_requested', newValues: $movement->getAttributes(), reason: trim($reason),
            );

            return $movement;
        });
    }

    public function approveUsage(ReserveMovement $movement, User $actor): ReserveMovement
    {
        if (! $actor->hasRole('direction')) {
            throw new AuthorizationException('Seule la direction approuve une utilisation de réserve.');
        }

        return DB::transaction(function () use ($movement, $actor): ReserveMovement {
            $locked = ReserveMovement::query()->whereKey($movement->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->type !== ReserveMovementType::Usage || $locked->approval_state !== 'pending') {
                throw ValidationException::withMessages(['reserve' => 'Cette utilisation n’attend plus d’approbation.']);
            }
            if ((int) $locked->created_by === (int) $actor->getKey() || (int) $locked->first_approved_by === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['approval' => 'Les deux approbateurs doivent être distincts du demandeur et entre eux.']);
            }
            $oldValues = $locked->getRawOriginal();
            if ($locked->first_approved_by === null) {
                $locked->first_approved_by = $actor->getKey();
                $action = 'reserve_usage_first_approved';
            } else {
                if ($locked->movement_amount > $this->currentAmount()) {
                    throw ValidationException::withMessages(['reserve' => 'Le montant demandé dépasse la réserve disponible.']);
                }
                $locked->second_approved_by = $actor->getKey();
                $locked->approval_state = 'approved';
                $locked->approved_at = CarbonImmutable::now('UTC');
                $action = 'reserve_usage_approved';
            }
            $this->auditLogger->runExplicitly(
                auditable: $locked, operation: fn (): bool => $locked->saveOrFail(),
                actorId: (int) $actor->getKey(), actorLabel: $this->actorLabel($actor), action: $action,
                oldValues: $oldValues, newValues: $locked->getAttributes(), reason: (string) $locked->reason,
            );

            return $locked;
        });
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
