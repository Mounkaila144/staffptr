<?php

namespace App\Services\Finance;

use App\Models\Finance\FixedCharge;
use App\Models\Identity\User;
use App\Services\Platform\SettingsService;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class FixedChargeService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private SettingsService $settingsService,
    ) {}

    public function currentBaseAmount(): int
    {
        return (int) FixedCharge::query()->active()->sum('monthly_amount');
    }

    public function reserveObjectiveAmount(): int
    {
        return $this->currentBaseAmount() * $this->settingsService->reserveTargetMonths();
    }

    /**
     * @return list<array{id: int, label: string, monthly_amount: int, monthly_amount_label: string, is_active: bool}>
     */
    public function forManagement(): array
    {
        $result = [];

        foreach (FixedCharge::query()->orderByDesc('is_active')->orderBy('label')->get() as $charge) {
            $result[] = [
                'id' => (int) $charge->getKey(),
                'label' => (string) $charge->label,
                'monthly_amount' => (int) $charge->monthly_amount,
                'monthly_amount_label' => Money::from((int) $charge->monthly_amount)->format(),
                'is_active' => (bool) $charge->is_active,
            ];
        }

        return $result;
    }

    /**
     * @return array{current_base_amount: int, proposed_base_amount: int, current_objective_amount: int, proposed_objective_amount: int, delta_amount: int, target_months: int, current_objective: string, proposed_objective: string, consequence: string}
     */
    public function preview(?FixedCharge $charge, int $monthlyAmount, bool $isActive): array
    {
        $currentBase = $this->currentBaseAmount();
        $currentChargeAmount = $charge !== null && $charge->is_active
            ? (int) $charge->monthly_amount
            : 0;
        $proposedBase = $currentBase - $currentChargeAmount + ($isActive ? $monthlyAmount : 0);
        $targetMonths = $this->settingsService->reserveTargetMonths();
        $currentObjective = $currentBase * $targetMonths;
        $proposedObjective = $proposedBase * $targetMonths;
        $delta = $proposedObjective - $currentObjective;

        return [
            'current_base_amount' => $currentBase,
            'proposed_base_amount' => $proposedBase,
            'current_objective_amount' => $currentObjective,
            'proposed_objective_amount' => $proposedObjective,
            'delta_amount' => $delta,
            'target_months' => $targetMonths,
            'current_objective' => Money::from($currentObjective)->format(),
            'proposed_objective' => Money::from($proposedObjective)->format(),
            'consequence' => $delta === 0
                ? "L'objectif de réserve ne change pas."
                : sprintf(
                    "L'objectif de réserve passera de %s à %s (%s%s).",
                    Money::from($currentObjective)->format(),
                    Money::from($proposedObjective)->format(),
                    $delta > 0 ? '+' : '−',
                    Money::from(abs($delta))->format(),
                ),
        ];
    }

    public function create(string $label, int $monthlyAmount, bool $isActive, User $actor): FixedCharge
    {
        $charge = new FixedCharge([
            'label' => trim($label),
            'monthly_amount' => $monthlyAmount,
            'is_active' => $isActive,
        ]);

        return $this->persist($charge, $actor, 'fixed_charge_created', "Création d'une charge fixe.");
    }

    public function update(
        FixedCharge $charge,
        string $label,
        int $monthlyAmount,
        bool $isActive,
        User $actor,
    ): FixedCharge {
        return DB::connection($charge->getConnectionName())->transaction(function () use (
            $charge,
            $label,
            $monthlyAmount,
            $isActive,
            $actor,
        ): FixedCharge {
            $locked = FixedCharge::query()->whereKey($charge->getKey())->lockForUpdate()->firstOrFail();
            $oldValues = Arr::only($locked->getRawOriginal(), ['label', 'monthly_amount', 'is_active']);
            $locked->fill([
                'label' => trim($label),
                'monthly_amount' => $monthlyAmount,
                'is_active' => $isActive,
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: fn (): bool => $locked->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'fixed_charge_updated',
                oldValues: $oldValues,
                newValues: Arr::only($locked->getAttributes(), ['label', 'monthly_amount', 'is_active']),
                reason: "Modification d'une charge fixe.",
            );

            $this->forgetDerivedValues();

            return $locked;
        });
    }

    public function setActive(FixedCharge $charge, bool $isActive, User $actor): FixedCharge
    {
        return DB::connection($charge->getConnectionName())->transaction(function () use ($charge, $isActive, $actor): FixedCharge {
            $locked = FixedCharge::query()->whereKey($charge->getKey())->lockForUpdate()->firstOrFail();
            $oldValues = ['is_active' => (bool) $locked->is_active];
            $locked->is_active = $isActive;

            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: fn (): bool => $locked->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: $isActive ? 'fixed_charge_activated' : 'fixed_charge_deactivated',
                oldValues: $oldValues,
                newValues: ['is_active' => $isActive],
                reason: $isActive ? 'Réactivation de la charge fixe.' : 'Désactivation de la charge fixe.',
            );

            $this->forgetDerivedValues();

            return $locked;
        });
    }

    private function persist(FixedCharge $charge, User $actor, string $action, string $reason): FixedCharge
    {
        return DB::connection($charge->getConnectionName())->transaction(function () use ($charge, $actor, $action, $reason): FixedCharge {
            $this->auditLogger->runExplicitly(
                auditable: $charge,
                operation: fn (): bool => $charge->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: $action,
                newValues: $charge->getAttributes(),
                reason: $reason,
            );

            $this->forgetDerivedValues();

            return $charge;
        });
    }

    private function forgetDerivedValues(): void
    {
        Cache::forget('finance:fixed-charge-base');
        Cache::forget('finance:reserve-objective');
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
