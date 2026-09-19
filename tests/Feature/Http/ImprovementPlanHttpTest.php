<?php

namespace Tests\Feature\Http;

use App\Enums\ImprovementPlanState;
use App\Enums\ReviewObjectiveStatus;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Services\Accountability\ImprovementPlanService;
use App\Services\Accountability\WeeklyReviewService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Task 3 — parcours HTTP du plan d'accompagnement (AC 9 à 13, 43).
 */
class ImprovementPlanHttpTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    public function test_ac_9_a_plan_is_created_from_its_review(): void
    {
        [$manager, , $review] = $this->review();

        $this->actingAs($manager)
            ->post(route('improvement-plans.store', $review), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('improvement_plans', [
            'weekly_review_id' => $review->getKey(),
            'state' => ImprovementPlanState::EnCours->value,
        ]);
        $this->assertDatabaseCount('improvement_plan_actions', 2);
    }

    public function test_ac_9_a_duration_outside_the_bounds_is_refused_by_the_server(): void
    {
        [$manager, , $review] = $this->review();

        $this->actingAs($manager)
            ->from(route('weekly-reviews.show', $review))
            ->post(route('improvement-plans.store', $review), $this->payload(days: 21))
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('improvement_plans', 0);
    }

    /**
     * AC 11 : un pair est refusé par URL directe, en consultation comme en écriture.
     */
    public function test_ac_11_a_peer_is_refused_by_direct_url(): void
    {
        [$manager, , $review] = $this->review();
        $plan = app(ImprovementPlanService::class)->createFromReview($review, $manager, $this->serviceData());
        $peer = User::factory()->active()->withRole('employe')->create();

        $this->flushSession();
        $this->actingAs($peer)->get(route('improvement-plans.show', $plan))->assertForbidden();
        $this->actingAs($peer)
            ->patch(route('improvement-plans.close', $plan), ['observed_result' => 'Intrusion.'])
            ->assertForbidden();
    }

    public function test_ac_11_the_person_and_their_manager_read_the_plan(): void
    {
        [$manager, $subject, $review] = $this->review();
        $plan = app(ImprovementPlanService::class)->createFromReview($review, $manager, $this->serviceData());

        $this->actingAs($manager)
            ->get(route('improvement-plans.show', $plan))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Accountability/ImprovementPlans/Show')
                ->where('plan.duration_days', 10)
                ->has('plan.actions', 2)
                ->where('permissions.close', true));

        $this->flushSession();
        $this->actingAs($subject)
            ->get(route('improvement-plans.show', $plan))
            ->assertOk()
            // La personne accompagnée lit son plan mais ne le clôture pas elle-même.
            ->assertInertia(fn (Assert $page): Assert => $page->where('permissions.close', false));
    }

    /**
     * AC 12 et 43 : après la clôture, le compte est inchangé et la personne reste connectée.
     */
    public function test_ac_12_and_43_closing_leaves_the_account_untouched(): void
    {
        [$manager, $subject, $review] = $this->review();
        $plan = app(ImprovementPlanService::class)->createFromReview($review, $manager, $this->serviceData());
        $stateBefore = $subject->state;
        $rolesBefore = $subject->getRoleNames()->sort()->values()->all();

        $this->actingAs($manager)
            ->patch(route('improvement-plans.close', $plan), ['observed_result' => 'Le rythme attendu est retrouvé.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $subject->refresh()->unsetRelation('roles');

        $this->assertSame($stateBefore, $subject->state);
        $this->assertSame($rolesBefore, $subject->getRoleNames()->sort()->values()->all());
        $this->assertSame(ImprovementPlanState::Termine, $plan->refresh()->state);

        // La personne accompagnée accède toujours à l'application : aucune session révoquée.
        $this->flushSession();
        $this->actingAs($subject)->get(route('improvement-plans.index'))->assertOk();
    }

    public function test_ac_10_the_close_form_requires_the_observed_result(): void
    {
        [$manager, , $review] = $this->review();
        $plan = app(ImprovementPlanService::class)->createFromReview($review, $manager, $this->serviceData());

        $this->actingAs($manager)
            ->from(route('improvement-plans.show', $plan))
            ->patch(route('improvement-plans.close', $plan), ['observed_result' => ''])
            ->assertSessionHasErrors('observed_result');

        $this->assertSame(ImprovementPlanState::EnCours, $plan->refresh()->state);
    }

    /** @return array{0: User, 1: User, 2: WeeklyReview} */
    private function review(): array
    {
        $manager = User::factory()->active()->withRole('direction')->create();
        $subject = User::factory()->active()->withRole('employe')->withManager($manager)->create();
        $reviews = app(WeeklyReviewService::class);
        $review = $reviews->open($manager, $subject, CarbonImmutable::parse('2026-08-10', 'Africa/Niamey'));
        $reviews->recordObjective($review, Objective::factory()->create(['user_id' => $subject->getKey()]), [
            'result' => 'Le rythme a fléchi.',
            'evidence' => null,
            'status' => ReviewObjectiveStatus::PartiellementAtteint,
            'gap_cause' => 'Une dépendance externe a manqué.',
            'next_action' => 'Convenir un point hebdomadaire.',
        ], $manager);

        return [$manager, $subject, $review->refresh()];
    }

    /** @return array<string, mixed> */
    private function payload(int $days = 10): array
    {
        $start = CarbonImmutable::parse('2026-08-17', 'Africa/Niamey');

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $start->addDays($days - 1)->toDateString(),
            'support_provided' => 'Un point hebdomadaire de trente minutes est fourni.',
            'actions' => [
                ['description' => 'Préparer le point avec deux exemples.', 'due_date' => '2026-08-19'],
                ['description' => 'Signaler la difficulté dès qu’elle survient.', 'due_date' => '2026-08-24'],
            ],
        ];
    }

    /**
     * @return array{start_date: string, end_date: string, support_provided: string, actions: list<array{description: string, due_date: string}>}
     */
    private function serviceData(): array
    {
        /** @var array{start_date: string, end_date: string, support_provided: string, actions: list<array{description: string, due_date: string}>} $data */
        $data = $this->payload();

        return $data;
    }
}
