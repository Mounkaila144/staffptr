<?php

namespace App\Services\Finance;

use App\Enums\AlertLevel;
use App\Enums\CorrectionPlanState;
use App\Models\Finance\CorrectionPlan;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plan correctif du niveau orange (FR163, AC 11, AC 14 à 18).
 *
 * Le plan porte constat, actions, responsables, échéance et résultat attendu ; il est rattaché au
 * mois qui a déclenché l'orange et reste consultable ensuite, y compris quand l'alerte est
 * repassée au vert.
 *
 * Immuabilité : un plan validé n'est jamais réécrit ni supprimé. Une révision crée une nouvelle
 * version liée à la précédente par `previous_id`, avec son motif (SOC-03, architecture § 15.2).
 * Création, validation et révision écrivent leur audit **dans la même transaction** que
 * l'opération : l'échec de l'audit annule l'opération (SOC-02).
 */
final readonly class CorrectionPlanService
{
    private const TIMEZONE = 'Africa/Niamey';

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Plan courant d'un mois : la version la plus élevée. `null` si aucun plan n'a été enregistré,
     * ce qui est exactement la condition qui maintient les relances de `direction` (AC 16).
     */
    public function currentFor(DateTimeInterface|string|null $month = null): ?CorrectionPlan
    {
        return CorrectionPlan::query()
            ->forMonth($this->normalizeMonth($month))
            ->orderByDesc('version')
            ->first();
    }

    public function existsFor(DateTimeInterface|string|null $month = null): bool
    {
        return $this->currentFor($month) !== null;
    }

    /**
     * Historique complet d'un mois, de la première version à la dernière : aucune version n'est
     * masquée, la révision s'ajoute sans effacer (AC 15, AC 17).
     *
     * @return Collection<int, CorrectionPlan>
     */
    public function historyFor(DateTimeInterface|string|null $month = null): Collection
    {
        return CorrectionPlan::query()
            ->forMonth($this->normalizeMonth($month))
            ->with(['creator.person', 'validator.person'])
            ->orderBy('version')
            ->get();
    }

    /**
     * @param  array{finding: string, actions: string, responsibles: string, due_on: string, expected_result: string}  $attributes
     */
    public function create(DateTimeInterface|string|null $month, array $attributes, User $actor): CorrectionPlan
    {
        $observed = $this->normalizeMonth($month);

        return DB::transaction(function () use ($observed, $attributes, $actor): CorrectionPlan {
            if ($this->currentFor($observed) !== null) {
                throw ValidationException::withMessages([
                    'finding' => "Un plan correctif existe déjà pour ce mois. Enregistrez une révision plutôt qu'un nouveau plan.",
                ]);
            }

            $plan = new CorrectionPlan([
                ...$attributes,
                'month' => $observed->toDateString(),
                'version' => 1,
                'state' => CorrectionPlanState::Brouillon,
                'created_by' => $actor->getKey(),
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $plan,
                operation: fn (): bool => $plan->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'correction_plan_created',
                newValues: $plan->getAttributes(),
                reason: sprintf(
                    'Plan correctif enregistré pour le mois passé au niveau %s.',
                    AlertLevel::Orange->label(),
                ),
            );

            return $plan;
        });
    }

    /** Validation : le plan devient définitivement figé (AC 17, AC 18). */
    public function validate(CorrectionPlan $plan, User $actor): CorrectionPlan
    {
        return DB::transaction(function () use ($plan, $actor): CorrectionPlan {
            $locked = CorrectionPlan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->state->isFrozen()) {
                throw ValidationException::withMessages([
                    'state' => 'Ce plan correctif est déjà validé. Une révision crée une nouvelle version.',
                ]);
            }

            $locked->fill([
                'state' => CorrectionPlanState::Valide,
                'validated_by' => $actor->getKey(),
                'validated_at' => CarbonImmutable::now('UTC'),
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: fn (): bool => $locked->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'correction_plan_validated',
                oldValues: ['state' => CorrectionPlanState::Brouillon->value],
                newValues: ['state' => CorrectionPlanState::Valide->value],
                reason: 'Validation du plan correctif.',
            );

            return $locked;
        });
    }

    /**
     * Révision : une **nouvelle version** liée à la précédente. L'original n'est ni modifié ni
     * supprimé (AC 17).
     *
     * @param  array{finding: string, actions: string, responsibles: string, due_on: string, expected_result: string, revision_reason: string}  $attributes
     */
    public function revise(CorrectionPlan $plan, array $attributes, User $actor): CorrectionPlan
    {
        return DB::transaction(function () use ($plan, $attributes, $actor): CorrectionPlan {
            $previous = CorrectionPlan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();

            if (! $previous->state->isFrozen()) {
                throw ValidationException::withMessages([
                    'revision_reason' => "Un plan encore en brouillon se corrige directement : la révision n'a de sens qu'après validation.",
                ]);
            }

            if ($previous->revision()->exists()) {
                throw ValidationException::withMessages([
                    'revision_reason' => 'Ce plan a déjà été révisé. Repartez de sa dernière version.',
                ]);
            }

            $revision = new CorrectionPlan([
                ...$attributes,
                'month' => $previous->month->toDateString(),
                'version' => $previous->version + 1,
                'previous_id' => $previous->getKey(),
                'state' => CorrectionPlanState::Brouillon,
                'created_by' => $actor->getKey(),
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $revision,
                operation: fn (): bool => $revision->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'correction_plan_revised',
                oldValues: ['version' => $previous->version, 'plan_id' => (int) $previous->getKey()],
                newValues: $revision->getAttributes(),
                reason: $attributes['revision_reason'],
            );

            return $revision;
        });
    }

    private function normalizeMonth(DateTimeInterface|string|null $month): CarbonImmutable
    {
        if ($month === null) {
            return CarbonImmutable::now(self::TIMEZONE)->startOfMonth();
        }

        return CarbonImmutable::parse($month, self::TIMEZONE)->startOfMonth();
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
