<?php

namespace App\Services\Accountability;

use App\Enums\ReviewObjectiveStatus;
use App\Enums\WeeklyReviewState;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\WeeklyReview;
use App\Models\Accountability\WeeklyReviewObjective;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Task;
use App\Support\Auditing\AuditLogger;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Revue hebdomadaire factuelle. Le service agrège en lecture seule les faits de la semaine
 * produits par `Work` et `Accountability` : il n'écrit jamais dans un autre module que le sien
 * (source-tree.md, règle de couplage).
 */
final class WeeklyReviewService
{
    /**
     * Périodicité hebdomadaire par défaut : le vendredi, cinquième jour de la semaine civile
     * commençant le lundi (AC 1, RM-08).
     */
    private const DEFAULT_SCHEDULED_WEEKDAY_OFFSET = 4;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Lundi de la semaine civile de Niamey contenant la date fournie.
     */
    public function weekStartFor(CarbonImmutable $date): CarbonImmutable
    {
        return $date->setTimezone('Africa/Niamey')->startOfWeek()->startOfDay();
    }

    /**
     * Vendredi de la semaine, date de tenue par défaut de la revue (AC 1).
     */
    public function defaultScheduledOn(CarbonImmutable $weekStart): CarbonImmutable
    {
        return $weekStart->addDays(self::DEFAULT_SCHEDULED_WEEKDAY_OFFSET);
    }

    /**
     * Ouvre la revue d'un membre pour une semaine donnée. La procédure est identique quel que
     * soit le rôle de la personne évaluée, `direction` comprise (AC 5, 42).
     */
    public function open(User $reviewer, User $subject, CarbonImmutable $weekStart): WeeklyReview
    {
        $weekStart = $this->weekStartFor($weekStart);

        if ($subject->is($reviewer)) {
            throw ValidationException::withMessages([
                'subject_user_id' => 'Une revue est conduite par une autre personne que celle qui est évaluée.',
            ]);
        }

        if ($this->existsFor($subject, $weekStart)) {
            throw ValidationException::withMessages([
                'week_start_date' => 'Une revue existe déjà pour cette personne sur cette semaine.',
            ]);
        }

        $review = new WeeklyReview;

        return DB::connection($review->getConnectionName())->transaction(function () use ($reviewer, $subject, $weekStart): WeeklyReview {
            $review = new WeeklyReview;
            $review->fill([
                'subject_user_id' => $subject->getKey(),
                'reviewer_id' => $reviewer->getKey(),
                'week_start_date' => $weekStart->toDateString(),
                'scheduled_on' => $this->defaultScheduledOn($weekStart)->toDateString(),
                'state' => WeeklyReviewState::Brouillon,
            ]);
            $review->saveOrFail();

            $this->auditLogger->record(
                actorId: $reviewer->getKey(),
                actorLabel: $this->labelFor($reviewer),
                auditable: $review,
                action: 'weekly_review_opened',
                oldValues: null,
                newValues: [
                    'subject_user_id' => $subject->getKey(),
                    'week_start_date' => $weekStart->toDateString(),
                ],
                reason: 'Ouverture de la revue hebdomadaire.',
            );

            return $review;
        });
    }

    public function existsFor(User $subject, CarbonImmutable $weekStart): bool
    {
        return WeeklyReview::query()
            ->where('subject_user_id', $subject->getKey())
            ->whereDate('week_start_date', $this->weekStartFor($weekStart)->toDateString())
            ->exists();
    }

    /**
     * Faits de la semaine, présentés sans ressaisie (AC 2). Lecture seule sur `Work` et sur les
     * objets d'`Accountability` déjà livrés, strictement limitée à la personne évaluée : aucun
     * classement ni comparaison entre personnes n'est produit (AC 6, 45).
     *
     * @return array{objectives: list<array<string, mixed>>, tasks: list<array<string, mixed>>, daily_reports: list<array<string, mixed>>, blockers: list<array<string, mixed>>}
     */
    public function weekFacts(WeeklyReview $review): array
    {
        $weekStart = $review->week_start_date;
        $weekEnd = $weekStart->addDays(6);
        $subjectId = $review->subject_user_id;

        $objectives = Objective::query()
            ->where('user_id', $subjectId)
            ->where(fn (Builder $open): Builder => $open
                ->whereBetween('due_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->orWhereIn('state', $this->openObjectiveStates()))
            ->orderBy('due_date')
            ->get();

        $tasks = Task::query()
            ->where('assignee_id', $subjectId)
            ->whereBetween('due_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('due_date')
            ->get();

        $dailyReports = DailyReport::query()
            ->where('author_id', $subjectId)
            ->whereBetween('report_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('report_date')
            ->get();

        $blockers = Blocker::query()
            ->where('created_by', $subjectId)
            ->whereBetween('reported_on', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('reported_on')
            ->get();

        return [
            'objectives' => $objectives->map(fn (Objective $objective): array => [
                'id' => $objective->getKey(),
                'title' => $objective->title,
                'indicator' => $objective->indicator,
                'target_value' => $objective->target_value,
                'expected_evidence' => $objective->expected_evidence,
                'due_date' => $objective->due_date->format('d/m/Y'),
                'state' => $objective->state->value,
                'state_label' => $objective->state->label(),
            ])->values()->all(),
            'tasks' => $tasks->map(fn (Task $task): array => [
                'id' => $task->getKey(),
                'title' => $task->title,
                'due_date' => $task->due_date->format('d/m/Y'),
                'status' => $task->status->value,
                'status_label' => $task->status->label(),
            ])->values()->all(),
            'daily_reports' => $dailyReports->map(fn (DailyReport $report): array => [
                'id' => $report->getKey(),
                'date' => $report->report_date->format('d/m/Y'),
                'state' => $report->state->value,
                'state_label' => $report->state->label(),
            ])->values()->all(),
            'blockers' => $blockers->map(fn (Blocker $blocker): array => [
                'id' => $blocker->getKey(),
                'problem' => $blocker->problem,
                'urgency' => $blocker->urgency->value,
                'state' => $blocker->state->value,
                'reported_on' => $blocker->reported_on->format('d/m/Y'),
            ])->values()->all(),
        ];
    }

    /**
     * Enregistre, pour un objectif de la personne évaluée, le résultat, la preuve, le statut,
     * la cause de l'écart et la prochaine action (AC 3).
     *
     * @param  array{result: string, evidence: string|null, status: ReviewObjectiveStatus, gap_cause: string|null, next_action: string}  $entry
     */
    public function recordObjective(WeeklyReview $review, Objective $objective, array $entry, User $actor): WeeklyReviewObjective
    {
        $this->assertEditable($review);

        if ($objective->user_id !== $review->subject_user_id) {
            throw ValidationException::withMessages([
                'objective_id' => "Cet objectif n'appartient pas à la personne évaluée.",
            ]);
        }

        if ($entry['status']->requiresGapCause() && trim((string) $entry['gap_cause']) === '') {
            throw ValidationException::withMessages([
                'gap_cause' => "Indiquez la cause de l'écart constaté.",
            ]);
        }

        return DB::connection($review->getConnectionName())->transaction(function () use ($review, $objective, $entry, $actor): WeeklyReviewObjective {
            $record = WeeklyReviewObjective::query()
                ->where('weekly_review_id', $review->getKey())
                ->where('objective_id', $objective->getKey())
                ->first() ?? new WeeklyReviewObjective;
            $existed = $record->exists;
            $oldValues = $existed ? ['status' => $record->status->value, 'result' => $record->result] : null;

            $record->fill([
                'weekly_review_id' => $review->getKey(),
                'objective_id' => $objective->getKey(),
                'result' => $entry['result'],
                'evidence' => $entry['evidence'],
                'status' => $entry['status'],
                'gap_cause' => $entry['status']->requiresGapCause() ? $entry['gap_cause'] : null,
                'next_action' => $entry['next_action'],
            ]);
            $record->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $record,
                action: $existed ? 'weekly_review_objective_updated' : 'weekly_review_objective_recorded',
                oldValues: $oldValues,
                newValues: ['status' => $entry['status']->value, 'result' => $entry['result']],
                reason: "Constat de la revue hebdomadaire sur l'objectif.",
            );

            return $record;
        });
    }

    /**
     * Enregistre le commentaire de la personne évaluée ou celui du responsable, selon l'auteur
     * effectif (AC 4).
     */
    public function comment(WeeklyReview $review, User $actor, string $body): WeeklyReview
    {
        $this->assertEditable($review);
        $side = $this->sideFor($review, $actor);
        $column = $side === 'reviewee' ? 'reviewee_comment' : 'reviewer_comment';

        return DB::connection($review->getConnectionName())->transaction(function () use ($review, $actor, $body, $column): WeeklyReview {
            $locked = WeeklyReview::query()->whereKey($review->getKey())->lockForUpdate()->firstOrFail();
            $oldValues = [$column => $locked->{$column}];
            $locked->{$column} = $body;
            $locked->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $locked,
                action: 'weekly_review_commented',
                oldValues: $oldValues,
                newValues: [$column => $body],
                reason: 'Commentaire porté à la revue hebdomadaire.',
            );

            return $locked;
        });
    }

    /**
     * Ouvre la phase de validation : la revue attend désormais les deux signatures.
     */
    public function submitForValidation(WeeklyReview $review, User $actor): WeeklyReview
    {
        $this->assertEditable($review);

        if ($review->state !== WeeklyReviewState::Brouillon) {
            throw ValidationException::withMessages(['review' => 'Cette revue attend déjà les validations.']);
        }

        if ($review->objectiveEntries()->doesntExist()) {
            throw ValidationException::withMessages([
                'review' => 'Renseignez au moins un objectif avant de soumettre la revue.',
            ]);
        }

        return DB::connection($review->getConnectionName())->transaction(function () use ($review, $actor): WeeklyReview {
            $locked = WeeklyReview::query()->whereKey($review->getKey())->lockForUpdate()->firstOrFail();
            $locked->state = WeeklyReviewState::EnAttenteValidation;
            $locked->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $locked,
                action: 'weekly_review_submitted',
                oldValues: ['state' => WeeklyReviewState::Brouillon->value],
                newValues: ['state' => $locked->state->value],
                reason: 'Revue hebdomadaire soumise aux deux validations.',
            );

            return $locked;
        });
    }

    /**
     * Validation électronique d'une des deux parties : horodatée et nominative (AC 4, 42).
     * Lorsque les deux parties se sont prononcées, la revue passe à `validee` et devient figée
     * (AC 7).
     */
    public function validateAs(WeeklyReview $review, User $actor): WeeklyReview
    {
        if ($review->state === WeeklyReviewState::Validee) {
            throw ValidationException::withMessages(['review' => 'Cette revue est validée : elle ne peut plus être modifiée.']);
        }

        if ($review->state !== WeeklyReviewState::EnAttenteValidation) {
            throw ValidationException::withMessages(['review' => "Cette revue n'est pas encore soumise à validation."]);
        }

        $side = $this->sideFor($review, $actor);

        return DB::connection($review->getConnectionName())->transaction(function () use ($review, $actor, $side): WeeklyReview {
            $locked = WeeklyReview::query()->whereKey($review->getKey())->lockForUpdate()->firstOrFail();
            $atColumn = $side.'_validated_at';
            $byColumn = $side.'_validated_by';

            if ($locked->{$atColumn} !== null) {
                throw ValidationException::withMessages(['review' => 'Vous avez déjà validé cette revue.']);
            }

            $oldState = $locked->state;
            $locked->{$atColumn} = CarbonImmutable::now('UTC');
            $locked->{$byColumn} = $actor->getKey();

            if ($locked->hasBothValidations()) {
                $locked->state = WeeklyReviewState::Validee;
            }

            $locked->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $locked,
                action: $side === 'reviewee' ? 'weekly_review_validated_by_reviewee' : 'weekly_review_validated_by_reviewer',
                oldValues: ['state' => $oldState->value],
                newValues: [
                    'state' => $locked->state->value,
                    $byColumn => $actor->getKey(),
                    $atColumn => $locked->{$atColumn}->format('Y-m-d H:i:s.v'),
                ],
                reason: 'Validation électronique de la revue hebdomadaire.',
            );

            return $locked;
        });
    }

    /**
     * Historique consultable des revues visibles de l'acteur (AC 7).
     *
     * @return LengthAwarePaginator<int, WeeklyReview>
     */
    public function history(User $actor, ?User $subject = null, int $perPage = 15): LengthAwarePaginator
    {
        return WeeklyReview::query()
            ->visibleTo($actor)
            ->when($subject instanceof User, fn (Builder $query): Builder => $query->where('subject_user_id', $subject?->getKey()))
            ->with(['subject.person', 'reviewer.person', 'revieweeValidator.person', 'reviewerValidator.person'])
            ->orderByDesc('week_start_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Charge utile d'une revue, destinée aux props Inertia.
     *
     * @return array<string, mixed>
     */
    public function payload(WeeklyReview $review): array
    {
        $review->loadMissing([
            'subject.person', 'reviewer.person', 'revieweeValidator.person', 'reviewerValidator.person',
            'objectiveEntries.objective',
        ]);

        return [
            'id' => $review->getKey(),
            'subject' => $this->labelFor($review->subject),
            'reviewer' => $this->labelFor($review->reviewer),
            'week_start_date' => $review->week_start_date->format('d/m/Y'),
            'scheduled_on' => $review->scheduled_on->format('d/m/Y'),
            'state' => $review->state->value,
            'state_label' => $review->state->label(),
            'is_editable' => $review->isEditable(),
            'reviewee_comment' => $review->reviewee_comment,
            'reviewer_comment' => $review->reviewer_comment,
            'reviewee_validation' => $this->validationPayload(
                $review->reviewee_validated_at,
                $review->revieweeValidator,
            ),
            'reviewer_validation' => $this->validationPayload(
                $review->reviewer_validated_at,
                $review->reviewerValidator,
            ),
            'objective_entries' => $review->objectiveEntries->map(fn (WeeklyReviewObjective $entry): array => [
                'id' => $entry->getKey(),
                'objective_id' => $entry->objective_id,
                'objective_title' => $entry->objective->title,
                'result' => $entry->result,
                'evidence' => $entry->evidence,
                'status' => $entry->status->value,
                'status_label' => $entry->status->label(),
                'gap_cause' => $entry->gap_cause,
                'next_action' => $entry->next_action,
            ])->values()->all(),
        ];
    }

    /**
     * Une revue validée est figée : aucune écriture n'est plus acceptée (AC 7).
     */
    public function assertEditable(WeeklyReview $review): void
    {
        if (! $review->isEditable()) {
            throw ValidationException::withMessages([
                'review' => 'Cette revue est validée : elle ne peut plus être modifiée.',
            ]);
        }
    }

    /**
     * Détermine de quel côté de la revue se trouve l'acteur. `direction` n'a pas de voie
     * dérobée : elle valide comme responsable seulement lorsqu'elle conduit la revue.
     */
    private function sideFor(WeeklyReview $review, User $actor): string
    {
        if ($review->subject_user_id === $actor->getKey()) {
            return 'reviewee';
        }

        if ($review->reviewer_id === $actor->getKey()) {
            return 'reviewer';
        }

        throw ValidationException::withMessages([
            'review' => "Seules les deux parties de la revue peuvent s'y prononcer.",
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function validationPayload(?CarbonImmutable $validatedAt, ?User $validator): array
    {
        return [
            'validated_at' => $validatedAt instanceof CarbonImmutable ? DateTimeFormatter::format($validatedAt) : null,
            'validated_by' => $validator instanceof User ? $this->labelFor($validator) : null,
        ];
    }

    /**
     * États d'objectif encore ouverts au cours de la semaine.
     *
     * @return list<string>
     */
    private function openObjectiveStates(): array
    {
        return ['valide', 'en_cours', 'bloque'];
    }

    private function labelFor(User $user): string
    {
        return $user->person()->value('full_name') ?? "Compte #{$user->getKey()}";
    }
}
