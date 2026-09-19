<?php

namespace Tests\Feature;

use App\Enums\ReviewObjectiveStatus;
use App\Enums\WeeklyReviewState;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Task;
use App\Services\Accountability\WeeklyReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Task 2 — revue hebdomadaire factuelle et doubles validations (AC 1 à 8, 42, 45).
 */
class WeeklyReviewLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** Lundi et vendredi de la semaine de référence, en dates civiles de Niamey. */
    private const WEEK_START = '2026-08-10';

    private const WEEK_FRIDAY = '2026-08-14';

    public function test_ac_1_a_review_opens_for_a_team_member_and_defaults_to_friday(): void
    {
        [$reviewer, $subject] = $this->team();

        $review = $this->service()->open($reviewer, $subject, $this->weekStart());

        $this->assertSame($subject->getKey(), $review->subject_user_id);
        $this->assertSame($reviewer->getKey(), $review->reviewer_id);
        $this->assertSame(self::WEEK_START, $review->week_start_date->toDateString());
        $this->assertSame(self::WEEK_FRIDAY, $review->scheduled_on->toDateString());
        $this->assertSame('vendredi', $review->scheduled_on->locale('fr')->dayName);
        $this->assertSame(WeeklyReviewState::Brouillon, $review->state);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $review->getKey(),
            'action' => 'weekly_review_opened',
        ]);
    }

    public function test_ac_1_a_second_review_for_the_same_person_and_week_is_refused(): void
    {
        [$reviewer, $subject] = $this->team();
        $this->service()->open($reviewer, $subject, $this->weekStart());

        $this->expectException(ValidationException::class);

        $this->service()->open($reviewer, $subject, $this->weekStart());
    }

    public function test_ac_1_nobody_reviews_themselves(): void
    {
        $actor = User::factory()->active()->withRole('direction')->create();

        $this->expectException(ValidationException::class);

        $this->service()->open($actor, $actor, $this->weekStart());
    }

    public function test_ac_2_the_week_facts_are_gathered_without_any_re_entry(): void
    {
        [$reviewer, $subject] = $this->team();
        $objective = Objective::factory()->create([
            'user_id' => $subject->getKey(),
            'due_date' => '2026-08-13',
        ]);
        Task::factory()->create(['assignee_id' => $subject->getKey(), 'due_date' => '2026-08-12']);
        DailyReport::factory()->create(['author_id' => $subject->getKey(), 'report_date' => '2026-08-11']);
        Blocker::factory()->create(['created_by' => $subject->getKey(), 'reported_on' => '2026-08-11']);
        // Faits hors de la semaine concernée : ils ne doivent pas remonter.
        Task::factory()->create(['assignee_id' => $subject->getKey(), 'due_date' => '2026-09-01']);
        DailyReport::factory()->create(['author_id' => $subject->getKey(), 'report_date' => '2026-07-01']);

        $review = $this->service()->open($reviewer, $subject, $this->weekStart());
        $facts = $this->service()->weekFacts($review);

        $this->assertCount(1, $facts['tasks']);
        $this->assertCount(1, $facts['daily_reports']);
        $this->assertCount(1, $facts['blockers']);
        $this->assertSame($objective->getKey(), $facts['objectives'][0]['id']);
        // Aucune écriture inter-module : les faits sont lus, jamais recopiés.
        $this->assertDatabaseCount('weekly_review_objectives', 0);
    }

    public function test_ac_3_each_objective_records_result_evidence_status_gap_and_next_action(): void
    {
        [$reviewer, $subject, $review] = $this->openedReview();
        $objective = Objective::factory()->create(['user_id' => $subject->getKey()]);

        $entry = $this->service()->recordObjective($review, $objective, [
            'result' => 'Deux livrables sur trois sont remis.',
            'evidence' => 'Documents partagés le jeudi.',
            'status' => ReviewObjectiveStatus::PartiellementAtteint,
            'gap_cause' => 'Une dépendance externe est arrivée tardivement.',
            'next_action' => 'Remettre le troisième livrable lundi.',
        ], $reviewer);

        $this->assertSame(ReviewObjectiveStatus::PartiellementAtteint, $entry->status);
        $this->assertSame('Une dépendance externe est arrivée tardivement.', $entry->gap_cause);
        $this->assertSame('Documents partagés le jeudi.', $entry->evidence);
        $this->assertSame('Remettre le troisième livrable lundi.', $entry->next_action);
        $this->assertDatabaseHas('audit_logs', ['action' => 'weekly_review_objective_recorded']);
    }

    public function test_ac_3_a_gap_without_its_cause_is_refused(): void
    {
        [$reviewer, $subject, $review] = $this->openedReview();
        $objective = Objective::factory()->create(['user_id' => $subject->getKey()]);

        $this->expectException(ValidationException::class);

        $this->service()->recordObjective($review, $objective, [
            'result' => 'Rien de remis.',
            'evidence' => null,
            'status' => ReviewObjectiveStatus::NonAtteint,
            'gap_cause' => '   ',
            'next_action' => 'Reprendre le sujet.',
        ], $reviewer);
    }

    public function test_ac_3_an_objective_of_someone_else_cannot_enter_the_review(): void
    {
        [$reviewer, , $review] = $this->openedReview();
        $foreign = Objective::factory()->create(['user_id' => User::factory()->active()->create()->getKey()]);

        $this->expectException(ValidationException::class);

        $this->service()->recordObjective($review, $foreign, $this->entry(), $reviewer);
    }

    public function test_ac_4_both_comments_and_both_nominative_timestamped_validations_are_recorded(): void
    {
        [$reviewer, $subject, $review] = $this->reviewReadyForValidation();

        $this->service()->comment($review, $subject, 'La semaine a été dense mais tenue.');
        $this->service()->comment($review, $reviewer, 'Le rythme est correct, la preuve est claire.');
        $afterReviewee = $this->service()->validateAs($review->refresh(), $subject);

        // Une seule des deux parties s'est prononcée : la revue reste ouverte.
        $this->assertSame(WeeklyReviewState::EnAttenteValidation, $afterReviewee->state);
        $this->assertFalse($afterReviewee->hasBothValidations());

        $validated = $this->service()->validateAs($afterReviewee->refresh(), $reviewer);

        $this->assertSame(WeeklyReviewState::Validee, $validated->state);
        $this->assertTrue($validated->hasBothValidations());
        $this->assertSame($subject->getKey(), $validated->reviewee_validated_by);
        $this->assertSame($reviewer->getKey(), $validated->reviewer_validated_by);
        $this->assertNotNull($validated->reviewee_validated_at);
        $this->assertNotNull($validated->reviewer_validated_at);
        $this->assertSame('La semaine a été dense mais tenue.', $validated->reviewee_comment);
        $this->assertSame('Le rythme est correct, la preuve est claire.', $validated->reviewer_comment);
        $this->assertDatabaseHas('audit_logs', ['action' => 'weekly_review_validated_by_reviewee']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'weekly_review_validated_by_reviewer']);
    }

    public function test_ac_4_the_same_party_cannot_validate_twice(): void
    {
        [, $subject, $review] = $this->reviewReadyForValidation();
        $this->service()->validateAs($review, $subject);

        $this->expectException(ValidationException::class);

        $this->service()->validateAs($review->refresh(), $subject);
    }

    public function test_ac_4_a_third_party_cannot_validate_the_review(): void
    {
        [, , $review] = $this->reviewReadyForValidation();
        $outsider = User::factory()->active()->withRole('direction')->create();

        $this->expectException(ValidationException::class);

        $this->service()->validateAs($review, $outsider);
    }

    public function test_ac_5_and_42_a_direction_account_follows_the_very_same_procedure(): void
    {
        // Un associé est évalué comme tout le monde, par un autre compte `direction`.
        $associate = User::factory()->active()->withRole('direction')->create();
        $peer = User::factory()->active()->withRole('direction')->create();

        $review = $this->service()->open($peer, $associate, $this->weekStart());
        $objective = Objective::factory()->create(['user_id' => $associate->getKey()]);
        $this->service()->recordObjective($review, $objective, $this->entry(), $peer);
        $this->service()->submitForValidation($review->refresh(), $peer);
        $this->service()->validateAs($review->refresh(), $associate);
        $validated = $this->service()->validateAs($review->refresh(), $peer);

        $this->assertTrue($associate->hasRole('direction'));
        $this->assertSame(WeeklyReviewState::Validee, $validated->state);
        $this->assertSame($associate->getKey(), $validated->reviewee_validated_by);
        $this->assertSame($peer->getKey(), $validated->reviewer_validated_by);
    }

    public function test_ac_6_and_45_the_review_never_exposes_a_ranking_between_people(): void
    {
        [$reviewer, $subject, $review] = $this->openedReview();
        $objective = Objective::factory()->create(['user_id' => $subject->getKey()]);
        $this->service()->recordObjective($review, $objective, $this->entry(), $reviewer);
        // Un pair produit exactement les mêmes faits la même semaine.
        $peer = User::factory()->active()->withRole('employe')->withManager($reviewer)->create();
        Task::factory()->create(['assignee_id' => $peer->getKey(), 'due_date' => '2026-08-12']);

        $facts = $this->service()->weekFacts($review->refresh());
        $payload = $this->service()->payload($review);
        $serialized = json_encode($facts + $payload, JSON_THROW_ON_ERROR);

        // Les faits ne portent que la personne évaluée : aucune donnée d'un pair, donc aucun
        // classement possible.
        $this->assertCount(0, $facts['tasks']);
        $this->assertStringNotContainsString((string) $peer->getKey(), (string) json_encode($facts, JSON_THROW_ON_ERROR));
        foreach (['rang', 'ranking', 'classement', 'score', 'note_globale', 'position'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serialized);
        }
    }

    public function test_ac_7_a_validated_review_is_frozen(): void
    {
        [$reviewer, $subject, $review] = $this->reviewReadyForValidation();
        $this->service()->validateAs($review, $subject);
        $validated = $this->service()->validateAs($review->refresh(), $reviewer);

        $this->assertFalse($validated->isEditable());

        foreach ([
            fn (): mixed => $this->service()->comment($validated, $reviewer, 'Ajout tardif.'),
            fn (): mixed => $this->service()->recordObjective(
                $validated,
                Objective::factory()->create(['user_id' => $subject->getKey()]),
                $this->entry(),
                $reviewer,
            ),
            fn (): mixed => $this->service()->validateAs($validated, $reviewer),
        ] as $writeAttempt) {
            try {
                $writeAttempt();
                $this->fail('Une revue validée ne doit accepter aucune écriture.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_ac_7_history_is_readable_and_scoped_to_the_two_parties(): void
    {
        [$reviewer, $subject] = $this->team();
        $this->service()->open($reviewer, $subject, $this->weekStart());
        $this->service()->open($reviewer, $subject, $this->weekStart()->subWeek());
        $outsider = User::factory()->active()->withRole('employe')->create();

        $this->assertSame(2, $this->service()->history($subject)->total());
        $this->assertSame(2, $this->service()->history($reviewer)->total());
        $this->assertSame(0, $this->service()->history($outsider)->total());
        $this->assertSame(2, $this->service()->history(
            User::factory()->active()->withRole('direction')->create(),
        )->total());
    }

    public function test_ac_7_a_review_cannot_be_submitted_without_a_single_objective(): void
    {
        [$reviewer, , $review] = $this->openedReview();

        $this->expectException(ValidationException::class);

        $this->service()->submitForValidation($review, $reviewer);
    }

    private function service(): WeeklyReviewService
    {
        return app(WeeklyReviewService::class);
    }

    private function weekStart(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::WEEK_START, 'Africa/Niamey');
    }

    /** @return array{0: User, 1: User} */
    private function team(): array
    {
        $reviewer = User::factory()->active()->withRole('tuteur')->create();
        $subject = User::factory()->active()->withRole('employe')->withManager($reviewer)->create();

        return [$reviewer, $subject];
    }

    /** @return array{0: User, 1: User, 2: WeeklyReview} */
    private function openedReview(): array
    {
        [$reviewer, $subject] = $this->team();

        return [$reviewer, $subject, $this->service()->open($reviewer, $subject, $this->weekStart())];
    }

    /** @return array{0: User, 1: User, 2: WeeklyReview} */
    private function reviewReadyForValidation(): array
    {
        [$reviewer, $subject, $review] = $this->openedReview();
        $objective = Objective::factory()->create(['user_id' => $subject->getKey()]);
        $this->service()->recordObjective($review, $objective, $this->entry(), $reviewer);
        $this->service()->submitForValidation($review->refresh(), $reviewer);

        return [$reviewer, $subject, $review->refresh()];
    }

    /** @return array{result: string, evidence: string|null, status: ReviewObjectiveStatus, gap_cause: string|null, next_action: string} */
    private function entry(): array
    {
        return [
            'result' => 'Le résultat attendu est atteint.',
            'evidence' => 'Document de synthèse partagé.',
            'status' => ReviewObjectiveStatus::Atteint,
            'gap_cause' => null,
            'next_action' => 'Poursuivre sur le même rythme.',
        ];
    }
}
