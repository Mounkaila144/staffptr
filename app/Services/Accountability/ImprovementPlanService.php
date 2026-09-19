<?php

namespace App\Services\Accountability;

use App\Enums\ImprovementPlanState;
use App\Models\Accountability\ImprovementPlan;
use App\Models\Accountability\ImprovementPlanAction;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plan d'amélioration : dispositif de soutien attaché à une revue hebdomadaire.
 *
 * Sa clôture est volontairement inerte. Elle n'écrit ni dans `Identity`, ni sur le rôle, les
 * permissions, l'état du compte ou les sessions de la personne accompagnée : aucune conséquence
 * disciplinaire n'est déclenchée automatiquement (AC 12, AC 43, RM-18, P3).
 */
final class ImprovementPlanService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Crée un plan depuis une revue, seule origine possible (AC 9).
     *
     * @param  array{start_date: string, end_date: string, support_provided: string, actions: list<array{description: string, due_date: string}>}  $data
     */
    public function createFromReview(WeeklyReview $review, User $actor, array $data): ImprovementPlan
    {
        $startDate = CarbonImmutable::parse($data['start_date'], 'Africa/Niamey')->startOfDay();
        $endDate = CarbonImmutable::parse($data['end_date'], 'Africa/Niamey')->startOfDay();

        $this->assertDurationWithinBounds($startDate, $endDate);

        if ($data['actions'] === []) {
            throw ValidationException::withMessages([
                'actions' => 'Décrivez au moins une action convenue.',
            ]);
        }

        $plan = new ImprovementPlan;

        return DB::connection($plan->getConnectionName())->transaction(function () use ($review, $actor, $data, $startDate, $endDate): ImprovementPlan {
            $plan = new ImprovementPlan;
            $plan->fill([
                'weekly_review_id' => $review->getKey(),
                'subject_user_id' => $review->subject_user_id,
                'created_by' => $actor->getKey(),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'support_provided' => $data['support_provided'],
                'state' => ImprovementPlanState::EnCours,
            ]);
            $plan->saveOrFail();

            foreach ($data['actions'] as $index => $action) {
                $record = new ImprovementPlanAction;
                $record->fill([
                    'improvement_plan_id' => $plan->getKey(),
                    'position' => $index + 1,
                    'description' => $action['description'],
                    'due_date' => CarbonImmutable::parse($action['due_date'], 'Africa/Niamey')->toDateString(),
                ]);
                $record->saveOrFail();
            }

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $plan,
                action: 'improvement_plan_created',
                oldValues: null,
                newValues: [
                    'subject_user_id' => $plan->subject_user_id,
                    'start_date' => $plan->start_date->toDateString(),
                    'end_date' => $plan->end_date->toDateString(),
                ],
                reason: "Mise en place d'un accompagnement convenu en revue.",
            );

            return $plan;
        });
    }

    /**
     * Clôture le plan en consignant le résultat constaté.
     *
     * Aucune écriture n'est faite ailleurs que sur le plan lui-même : le compte de la personne
     * accompagnée reste strictement dans l'état où la clôture l'a trouvé (AC 12, 43).
     */
    public function close(ImprovementPlan $plan, User $actor, string $observedResult): ImprovementPlan
    {
        if ($plan->state !== ImprovementPlanState::EnCours) {
            throw ValidationException::withMessages(['plan' => 'Ce plan est déjà clôturé.']);
        }

        return DB::connection($plan->getConnectionName())->transaction(function () use ($plan, $actor, $observedResult): ImprovementPlan {
            $locked = ImprovementPlan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();
            $locked->fill([
                'state' => ImprovementPlanState::Termine,
                'observed_result' => $observedResult,
                'closed_by' => $actor->getKey(),
                'closed_at' => CarbonImmutable::now('UTC'),
            ]);
            $locked->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $locked,
                action: 'improvement_plan_closed',
                oldValues: ['state' => ImprovementPlanState::EnCours->value],
                newValues: ['state' => $locked->state->value],
                reason: "Fin de l'accompagnement : résultat constaté consigné.",
            );

            return $locked;
        });
    }

    /**
     * Marque une action du plan comme réalisée.
     */
    public function completeAction(ImprovementPlanAction $action, User $actor): ImprovementPlanAction
    {
        $plan = $action->improvementPlan;

        if ($plan->state !== ImprovementPlanState::EnCours) {
            throw ValidationException::withMessages(['plan' => 'Ce plan est clôturé.']);
        }

        return DB::connection($action->getConnectionName())->transaction(function () use ($action, $actor): ImprovementPlanAction {
            $action->completed_at = CarbonImmutable::now('UTC');
            $action->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $action,
                action: 'improvement_plan_action_completed',
                oldValues: ['completed_at' => null],
                newValues: ['completed_at' => $action->completed_at->format('Y-m-d H:i:s.v')],
                reason: 'Action du plan réalisée.',
            );

            return $action;
        });
    }

    /**
     * Plans visibles de l'acteur : la personne concernée, son responsable et `direction` (AC 11).
     *
     * @return LengthAwarePaginator<int, ImprovementPlan>
     */
    public function visibleFor(User $actor, int $perPage = 15): LengthAwarePaginator
    {
        return ImprovementPlan::query()
            ->visibleTo($actor)
            ->with(['subject.person', 'creator.person', 'actions'])
            ->orderByDesc('start_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Bornes de durée vérifiées côté serveur, indépendamment de tout contrôle de formulaire
     * (AC 9).
     */
    public function assertDurationWithinBounds(CarbonImmutable $startDate, CarbonImmutable $endDate): void
    {
        if ($endDate->lessThan($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'La date de fin vient après la date de début.',
            ]);
        }

        $days = (int) $startDate->diffInDays($endDate) + 1;

        if ($days < ImprovementPlan::MINIMUM_DURATION_DAYS || $days > ImprovementPlan::MAXIMUM_DURATION_DAYS) {
            throw ValidationException::withMessages([
                'end_date' => sprintf(
                    'Un plan dure entre %d et %d jours. Celui-ci en compte %d.',
                    ImprovementPlan::MINIMUM_DURATION_DAYS,
                    ImprovementPlan::MAXIMUM_DURATION_DAYS,
                    $days,
                ),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ImprovementPlan $plan): array
    {
        $plan->loadMissing(['subject.person', 'creator.person', 'actions', 'weeklyReview']);

        return [
            'id' => $plan->getKey(),
            'subject' => $this->labelFor($plan->subject),
            'created_by' => $this->labelFor($plan->creator),
            'weekly_review_id' => $plan->weekly_review_id,
            'start_date' => $plan->start_date->format('d/m/Y'),
            'end_date' => $plan->end_date->format('d/m/Y'),
            'duration_days' => $plan->durationInDays(),
            'support_provided' => $plan->support_provided,
            'observed_result' => $plan->observed_result,
            'state' => $plan->state->value,
            'state_label' => $plan->state->label(),
            'closed_at' => $plan->closed_at instanceof CarbonImmutable ? DateTimeFormatter::format($plan->closed_at) : null,
            'actions' => $plan->actions->sortBy('position')->map(fn (ImprovementPlanAction $action): array => [
                'id' => $action->getKey(),
                'position' => $action->position,
                'description' => $action->description,
                'due_date' => $action->due_date->format('d/m/Y'),
                'completed' => $action->completed_at !== null,
            ])->values()->all(),
        ];
    }

    /**
     * @param  Builder<ImprovementPlan>  $query
     * @return Builder<ImprovementPlan>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('state', ImprovementPlanState::EnCours);
    }

    private function labelFor(User $user): string
    {
        return $user->person()->value('full_name') ?? "Compte #{$user->getKey()}";
    }
}
