<?php

namespace Tests\Feature\Http;

use App\Enums\ReviewObjectiveStatus;
use App\Enums\WeeklyReviewState;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Services\Accountability\WeeklyReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Task 2 — parcours HTTP de la revue hebdomadaire : portées serveur, refus par URL directe
 * et validation mobile de la personne évaluée (AC 1, 5, 6, 7, 8, 42, 45).
 */
class WeeklyReviewHttpTest extends TestCase
{
    use RefreshDatabase;

    private const WEEK_START = '2026-08-10';

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-14 09:00:00 Africa/Niamey');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_ac_1_a_manager_opens_a_review_for_a_team_member(): void
    {
        [$reviewer, $subject] = $this->team();

        $this->actingAs($reviewer)
            ->post(route('weekly-reviews.store'), [
                'subject_user_id' => $subject->getKey(),
                'week_start_date' => self::WEEK_START,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('weekly_reviews', [
            'subject_user_id' => $subject->getKey(),
            'reviewer_id' => $reviewer->getKey(),
            'state' => WeeklyReviewState::Brouillon->value,
        ]);
    }

    public function test_ac_1_a_manager_cannot_open_a_review_outside_their_team(): void
    {
        $reviewer = User::factory()->active()->withRole('tuteur')->create();
        $stranger = User::factory()->active()->withRole('employe')->create();

        $this->actingAs($reviewer)
            ->post(route('weekly-reviews.store'), [
                'subject_user_id' => $stranger->getKey(),
                'week_start_date' => self::WEEK_START,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('weekly_reviews', 0);
    }

    public function test_ac_5_direction_opens_a_review_for_an_associate(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $associate = User::factory()->active()->withRole('direction')->create();

        $this->actingAs($direction)
            ->post(route('weekly-reviews.store'), [
                'subject_user_id' => $associate->getKey(),
                'week_start_date' => self::WEEK_START,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('weekly_reviews', [
            'subject_user_id' => $associate->getKey(),
            'reviewer_id' => $direction->getKey(),
        ]);
    }

    public function test_ac_7_the_show_page_carries_the_review_and_the_week_facts(): void
    {
        [$reviewer, $subject, $review] = $this->openedReview();

        $this->actingAs($reviewer)
            ->get(route('weekly-reviews.show', $review))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Accountability/WeeklyReviews/Show')
                ->where('review.state', WeeklyReviewState::Brouillon->value)
                ->where('review.scheduled_on', '14/08/2026')
                ->has('facts.objectives')
                ->has('facts.tasks')
                ->has('facts.daily_reports')
                ->has('facts.blockers'));

        $this->flushSession();
        $this->actingAs($subject)->get(route('weekly-reviews.show', $review))->assertOk();
    }

    public function test_ac_5_and_31_a_peer_is_refused_by_direct_url(): void
    {
        [, , $review] = $this->openedReview();
        $peer = User::factory()->active()->withRole('employe')->create();

        $this->flushSession();
        $this->actingAs($peer)->get(route('weekly-reviews.show', $review))->assertForbidden();
        $this->actingAs($peer)
            ->post(route('weekly-reviews.comments.store', $review), ['body' => 'Intrusion.'])
            ->assertForbidden();
        $this->actingAs($peer)
            ->patch(route('weekly-reviews.validate', $review))
            ->assertForbidden();
    }

    public function test_ac_8_the_reviewed_person_validates_without_a_computer(): void
    {
        [$reviewer, $subject, $review] = $this->reviewReadyForValidation();

        // La personne évaluée commente puis valide depuis son téléphone : deux requêtes simples,
        // sans aucune étape réservée à un poste de travail.
        $this->actingAs($subject)
            ->post(route('weekly-reviews.comments.store', $review), ['body' => 'Je confirme les constats.'])
            ->assertRedirect();
        $this->actingAs($subject)
            ->patch(route('weekly-reviews.validate', $review))
            ->assertRedirect();

        $review->refresh();
        $this->assertSame($subject->getKey(), $review->reviewee_validated_by);
        $this->assertNotNull($review->reviewee_validated_at);
        $this->assertSame(WeeklyReviewState::EnAttenteValidation, $review->state);

        // `AuthenticateSession` invalide la session dès que l'utilisateur authentifié change
        // (PERM-08) : chaque partie se prononce donc depuis sa propre session.
        $this->flushSession();
        $this->actingAs($reviewer)
            ->patch(route('weekly-reviews.validate', $review))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(WeeklyReviewState::Validee, $review->refresh()->state);
    }

    public function test_ac_7_a_validated_review_refuses_every_further_write(): void
    {
        [$reviewer, $subject, $review] = $this->reviewReadyForValidation();
        $this->actingAs($subject)->patch(route('weekly-reviews.validate', $review));
        $this->flushSession();
        $this->actingAs($reviewer)->patch(route('weekly-reviews.validate', $review));

        $this->assertSame(WeeklyReviewState::Validee, $review->refresh()->state);

        $this->actingAs($reviewer)
            ->post(route('weekly-reviews.comments.store', $review), ['body' => 'Ajout tardif.'])
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->post(route('weekly-reviews.objectives.store', $review), [
                'objective_id' => Objective::factory()->create(['user_id' => $subject->getKey()])->getKey(),
                'result' => 'Correction tardive.',
                'status' => ReviewObjectiveStatus::Atteint->value,
                'next_action' => 'Rien.',
            ])
            ->assertForbidden();
    }

    public function test_ac_6_and_45_the_history_never_ranks_people(): void
    {
        [$reviewer, $subject] = $this->team();
        app(WeeklyReviewService::class)->open($reviewer, $subject, $this->weekStart());

        $this->actingAs($reviewer)
            ->get(route('weekly-reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Accountability/WeeklyReviews/Index')
                ->has('reviews.data', 1)
                ->missing('reviews.data.0.rank')
                ->missing('reviews.data.0.score'));
    }

    public function test_ac_3_the_form_request_refuses_an_incomplete_finding(): void
    {
        [$reviewer, $subject, $review] = $this->openedReview();

        $this->actingAs($reviewer)
            ->from(route('weekly-reviews.show', $review))
            ->post(route('weekly-reviews.objectives.store', $review), [
                'objective_id' => Objective::factory()->create(['user_id' => $subject->getKey()])->getKey(),
                'result' => '',
                'status' => ReviewObjectiveStatus::Atteint->value,
                'next_action' => '',
            ])
            ->assertSessionHasErrors(['result', 'next_action']);
    }

    private function weekStart(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::WEEK_START, 'Africa/Niamey');
    }

    /** @return array{0: User, 1: User} */
    private function team(): array
    {
        $reviewer = User::factory()->active()->withRole('direction')->create();
        $subject = User::factory()->active()->withRole('employe')->withManager($reviewer)->create();

        return [$reviewer, $subject];
    }

    /** @return array{0: User, 1: User, 2: WeeklyReview} */
    private function openedReview(): array
    {
        [$reviewer, $subject] = $this->team();
        $review = app(WeeklyReviewService::class)->open($reviewer, $subject, $this->weekStart());

        return [$reviewer, $subject, $review];
    }

    /** @return array{0: User, 1: User, 2: WeeklyReview} */
    private function reviewReadyForValidation(): array
    {
        [$reviewer, $subject, $review] = $this->openedReview();
        $service = app(WeeklyReviewService::class);
        $service->recordObjective(
            $review,
            Objective::factory()->create(['user_id' => $subject->getKey()]),
            [
                'result' => 'Le résultat attendu est atteint.',
                'evidence' => 'Document partagé.',
                'status' => ReviewObjectiveStatus::Atteint,
                'gap_cause' => null,
                'next_action' => 'Poursuivre.',
            ],
            $reviewer,
        );
        $service->submitForValidation($review->refresh(), $reviewer);

        return [$reviewer, $subject, $review->refresh()];
    }
}
