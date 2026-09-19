<?php

namespace App\Services\Accountability;

use App\Enums\InternshipChecklistType;
use App\Enums\InternshipEvaluationType;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipChecklistItem;
use App\Models\Accountability\InternshipEvaluation;
use App\Models\Accountability\InternshipPlan;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plan de stage, évaluations hebdomadaires et finale, checklist de sortie (AC 27 à 33).
 *
 * Une évaluation validée est définitivement figée : ni modifiable, ni supprimable (AC 32).
 * L'évaluation finale indique si les conditions d'attestation sont remplies, **sans produire
 * aucun document** en MVP (AC 29, FR89).
 */
final class InternshipEvaluationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly InternshipService $internships,
    ) {}

    /**
     * Enregistre ou met à jour le plan de stage (AC 27).
     *
     * @param  array{skills_to_learn: string, objectives: string, weekly_tasks: string, expected_evidence: string}  $data
     */
    public function savePlan(Internship $internship, User $actor, array $data): InternshipPlan
    {
        return DB::connection($internship->getConnectionName())->transaction(function () use ($internship, $actor, $data): InternshipPlan {
            $plan = InternshipPlan::query()->where('internship_id', $internship->getKey())->first() ?? new InternshipPlan;
            $existed = $plan->exists;

            $plan->fill([
                'internship_id' => $internship->getKey(),
                'skills_to_learn' => $data['skills_to_learn'],
                'objectives' => $data['objectives'],
                'weekly_tasks' => $data['weekly_tasks'],
                'expected_evidence' => $data['expected_evidence'],
            ]);
            $plan->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $plan,
                action: $existed ? 'internship_plan_updated' : 'internship_plan_created',
                oldValues: null,
                newValues: ['internship_id' => $internship->getKey()],
                reason: 'Plan de stage enregistré.',
            );

            return $plan;
        });
    }

    /**
     * Évaluation hebdomadaire enregistrée par le tuteur (AC 28).
     *
     * @param  array{observed_progress: string, evidence: string|null, next_steps: string|null}  $data
     */
    public function recordWeekly(
        Internship $internship,
        User $evaluator,
        CarbonImmutable $weekStart,
        array $data,
    ): InternshipEvaluation {
        $weekStart = $weekStart->setTimezone('Africa/Niamey')->startOfWeek()->startOfDay();
        $existing = InternshipEvaluation::query()
            ->where('internship_id', $internship->getKey())
            ->where('type', InternshipEvaluationType::Hebdomadaire)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->first();

        if ($existing instanceof InternshipEvaluation && ! $existing->isEditable()) {
            throw ValidationException::withMessages([
                'evaluation' => 'Cette évaluation est validée : elle ne peut plus être modifiée.',
            ]);
        }

        return DB::connection($internship->getConnectionName())->transaction(function () use ($internship, $evaluator, $weekStart, $data, $existing): InternshipEvaluation {
            $evaluation = $existing ?? new InternshipEvaluation;
            $existed = $evaluation->exists;

            $evaluation->fill([
                'internship_id' => $internship->getKey(),
                'evaluator_id' => $evaluator->getKey(),
                'type' => InternshipEvaluationType::Hebdomadaire,
                'week_start_date' => $weekStart->toDateString(),
                'observed_progress' => $data['observed_progress'],
                'evidence' => $data['evidence'],
                'next_steps' => $data['next_steps'],
            ]);
            $evaluation->saveOrFail();

            $this->auditLogger->record(
                actorId: $evaluator->getKey(),
                actorLabel: $this->labelFor($evaluator),
                auditable: $evaluation,
                action: $existed ? 'internship_evaluation_updated' : 'internship_evaluation_recorded',
                oldValues: null,
                newValues: ['week_start_date' => $weekStart->toDateString()],
                reason: 'Évaluation hebdomadaire du stagiaire.',
            );

            return $evaluation;
        });
    }

    /**
     * Évaluation finale (AC 29). Une seule par stage — l'unicité ne pouvant pas être portée par le
     * schéma, `week_start_date` étant nul et donc distinct pour MySQL comme pour SQLite, elle est
     * garantie ici.
     *
     * @param  array{observed_progress: string, evidence: string|null, next_steps: string|null}  $data
     */
    public function recordFinal(Internship $internship, User $evaluator, array $data): InternshipEvaluation
    {
        $existing = InternshipEvaluation::query()
            ->where('internship_id', $internship->getKey())
            ->where('type', InternshipEvaluationType::Finale)
            ->first();

        if ($existing instanceof InternshipEvaluation && ! $existing->isEditable()) {
            throw ValidationException::withMessages([
                'evaluation' => 'Cette évaluation est validée : elle ne peut plus être modifiée.',
            ]);
        }

        return DB::connection($internship->getConnectionName())->transaction(function () use ($internship, $evaluator, $data, $existing): InternshipEvaluation {
            $evaluation = $existing ?? new InternshipEvaluation;

            $evaluation->fill([
                'internship_id' => $internship->getKey(),
                'evaluator_id' => $evaluator->getKey(),
                'type' => InternshipEvaluationType::Finale,
                'week_start_date' => null,
                'observed_progress' => $data['observed_progress'],
                'evidence' => $data['evidence'],
                'next_steps' => $data['next_steps'],
                'certificate_conditions_met' => $this->certificateConditionsMet($internship),
            ]);
            $evaluation->saveOrFail();

            $this->auditLogger->record(
                actorId: $evaluator->getKey(),
                actorLabel: $this->labelFor($evaluator),
                auditable: $evaluation,
                action: 'internship_final_evaluation_recorded',
                oldValues: null,
                newValues: ['certificate_conditions_met' => $evaluation->certificate_conditions_met],
                reason: 'Évaluation finale du stage.',
            );

            return $evaluation;
        });
    }

    /**
     * Fige une évaluation : elle devient ni modifiable ni supprimable (AC 32).
     */
    public function validate(InternshipEvaluation $evaluation, User $actor): InternshipEvaluation
    {
        if (! $evaluation->isEditable()) {
            throw ValidationException::withMessages(['evaluation' => 'Cette évaluation est déjà validée.']);
        }

        return DB::connection($evaluation->getConnectionName())->transaction(function () use ($evaluation, $actor): InternshipEvaluation {
            $locked = InternshipEvaluation::query()->whereKey($evaluation->getKey())->lockForUpdate()->firstOrFail();
            $locked->validated_at = CarbonImmutable::now('UTC');
            $locked->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $locked,
                action: 'internship_evaluation_validated',
                oldValues: ['validated_at' => null],
                newValues: ['validated_at' => $locked->validated_at->format('Y-m-d H:i:s.v')],
                reason: 'Évaluation figée.',
            );

            return $locked;
        });
    }

    /**
     * Conditions d'attestation : un plan de stage, au moins une évaluation hebdomadaire, et une
     * checklist d'intégration entièrement effectuée. L'application les **indique** sans produire
     * de document (AC 29, FR89).
     */
    public function certificateConditionsMet(Internship $internship): bool
    {
        $hasPlan = InternshipPlan::query()->where('internship_id', $internship->getKey())->exists();
        $hasWeekly = InternshipEvaluation::query()
            ->where('internship_id', $internship->getKey())
            ->where('type', InternshipEvaluationType::Hebdomadaire)
            ->exists();
        $integrationPending = $internship->checklistItems()
            ->where('checklist_type', InternshipChecklistType::Integration)
            ->whereNull('completed_at')
            ->exists();

        return $hasPlan && $hasWeekly && ! $integrationPending;
    }

    /**
     * Clôture le stage : checklist de sortie générée, place du tuteur libérée (AC 30).
     */
    public function startExit(Internship $internship, User $actor): Internship
    {
        $ended = $this->internships->end($internship, $actor);
        $this->internships->generateChecklist($ended, InternshipChecklistType::Sortie);

        return $ended->refresh();
    }

    /**
     * Bloc « Dernière évaluation » du tableau de bord personnel (AC 33, FR166).
     *
     * @return array{title: string, status: string, detail: string, action_label: string, action_url: string}|null
     */
    public function lastEvaluationBlock(User $user): ?array
    {
        $internship = Internship::query()->where('user_id', $user->getKey())->first();

        if (! $internship instanceof Internship) {
            return null;
        }

        $evaluation = InternshipEvaluation::query()
            ->where('internship_id', $internship->getKey())
            ->orderByDesc('created_at')
            ->with('evaluator.person')
            ->first();

        if (! $evaluation instanceof InternshipEvaluation) {
            return [
                'title' => 'Dernière évaluation',
                'status' => 'Aucune évaluation pour l’instant',
                'detail' => 'Votre tuteur enregistrera votre première évaluation à la fin de la semaine.',
                'action_label' => 'Ouvrir mon dossier de stage',
                'action_url' => route('internships.show', $internship, absolute: false),
            ];
        }

        return [
            'title' => 'Dernière évaluation',
            'status' => $evaluation->type->label(),
            'detail' => sprintf(
                '%s, le %s.',
                $evaluation->evaluator->person->full_name,
                DateTimeFormatter::format($evaluation->created_at, 'd/m/Y'),
            ),
            'action_label' => 'Lire mon évaluation',
            'action_url' => route('internships.show', $internship, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dossier(Internship $internship): array
    {
        $internship->loadMissing([
            'intern.person', 'tutor.person', 'plan', 'evaluations.evaluator.person', 'checklistItems',
        ]);

        return [
            'id' => $internship->getKey(),
            'intern' => $internship->intern->person->full_name,
            'tutor' => $internship->tutor->person->full_name,
            'state' => $internship->state->value,
            'state_label' => $internship->state->label(),
            'start_date' => $internship->start_date->format('d/m/Y'),
            'end_date' => $internship->end_date?->format('d/m/Y'),
            'plan' => $internship->plan instanceof InternshipPlan ? [
                'skills_to_learn' => $internship->plan->skills_to_learn,
                'objectives' => $internship->plan->objectives,
                'weekly_tasks' => $internship->plan->weekly_tasks,
                'expected_evidence' => $internship->plan->expected_evidence,
            ] : null,
            'evaluations' => $internship->evaluations
                ->sortByDesc('created_at')
                ->map(fn (InternshipEvaluation $evaluation): array => [
                    'id' => $evaluation->getKey(),
                    'type' => $evaluation->type->value,
                    'type_label' => $evaluation->type->label(),
                    'week_start_date' => $evaluation->week_start_date?->format('d/m/Y'),
                    'evaluator' => $evaluation->evaluator->person->full_name,
                    'observed_progress' => $evaluation->observed_progress,
                    'evidence' => $evaluation->evidence,
                    'next_steps' => $evaluation->next_steps,
                    'certificate_conditions_met' => $evaluation->certificate_conditions_met,
                    'validated' => ! $evaluation->isEditable(),
                    'created_at' => DateTimeFormatter::format($evaluation->created_at, 'd/m/Y'),
                ])->values()->all(),
            'checklists' => collect(InternshipChecklistType::cases())
                ->mapWithKeys(fn (InternshipChecklistType $type): array => [
                    $type->value => $internship->checklistItems
                        ->where('checklist_type', $type)
                        ->sortBy('position')
                        ->map(fn (InternshipChecklistItem $item): array => [
                            'id' => $item->getKey(),
                            'label' => $item->label,
                            'completed' => $item->completed_at !== null,
                        ])->values()->all(),
                ])->all(),
            // L'attestation n'est jamais produite : l'application se borne à indiquer l'éligibilité.
            'certificate_conditions_met' => $this->certificateConditionsMet($internship),
        ];
    }

    private function labelFor(User $user): string
    {
        return $user->person()->value('full_name') ?? "Compte #{$user->getKey()}";
    }
}
