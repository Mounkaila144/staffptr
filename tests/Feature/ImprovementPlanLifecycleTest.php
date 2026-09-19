<?php

namespace Tests\Feature;

use App\Enums\ImprovementPlanState;
use App\Enums\ReviewObjectiveStatus;
use App\Http\Requests\Accountability\CloseImprovementPlanRequest;
use App\Http\Requests\Accountability\StoreImprovementPlanRequest;
use App\Models\Accountability\ImprovementPlan;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Models\Work\Objective;
use App\Services\Accountability\ImprovementPlanService;
use App\Services\Accountability\WeeklyReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Task 3 — le plan d'amélioration est un dispositif de soutien (AC 9 à 13, 43).
 */
class ImprovementPlanLifecycleTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    private const WEEK_START = '2026-08-10';

    public function test_ac_9_a_plan_is_created_from_a_review(): void
    {
        [$manager, $subject, $review] = $this->review();

        $plan = $this->plans()->createFromReview($review, $manager, $this->data());

        $this->assertSame($review->getKey(), $plan->weekly_review_id);
        $this->assertSame($subject->getKey(), $plan->subject_user_id);
        $this->assertSame($manager->getKey(), $plan->created_by);
        $this->assertSame(ImprovementPlanState::EnCours, $plan->state);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $plan->getKey(),
            'action' => 'improvement_plan_created',
        ]);
    }

    /**
     * Les deux bornes sont acceptées, tout ce qui les dépasse est refusé — et le refus vient du
     * service, indépendamment de tout contrôle de formulaire.
     */
    public function test_ac_9_duration_is_refused_server_side_outside_seven_to_fourteen_days(): void
    {
        [$manager, , $review] = $this->review();

        foreach ([7, 10, 14] as $acceptedDays) {
            $plan = $this->plans()->createFromReview(
                $review,
                $manager,
                $this->data(days: $acceptedDays),
            );
            $this->assertSame($acceptedDays, $plan->durationInDays());
        }

        foreach ([1, 6, 15, 30] as $refusedDays) {
            try {
                $this->plans()->createFromReview($review, $manager, $this->data(days: $refusedDays));
                $this->fail("Une durée de {$refusedDays} jours doit être refusée.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('end_date', $exception->errors());
                $this->assertStringContainsString('entre 7 et 14 jours', $exception->errors()['end_date'][0]);
            }
        }

        $this->assertSame(3, ImprovementPlan::query()->count());
    }

    public function test_ac_10_the_plan_carries_actions_support_dates_and_observed_result(): void
    {
        [$manager, , $review] = $this->review();

        $plan = $this->plans()->createFromReview($review, $manager, $this->data());

        $this->assertSame('Un point hebdomadaire de trente minutes est fourni.', $plan->support_provided);
        $this->assertSame('2026-08-17', $plan->start_date->toDateString());
        $this->assertSame('2026-08-26', $plan->end_date->toDateString());
        $this->assertSame(2, $plan->actions()->count());
        $this->assertNull($plan->observed_result);

        $closed = $this->plans()->close($plan, $manager, 'Le rythme attendu est retrouvé.');

        $this->assertSame('Le rythme attendu est retrouvé.', $closed->observed_result);
        $this->assertSame(ImprovementPlanState::Termine, $closed->state);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame($manager->getKey(), $closed->closed_by);
    }

    public function test_ac_11_the_plan_is_visible_to_the_person_their_manager_and_direction_only(): void
    {
        [$manager, $subject, $review] = $this->review();
        $this->plans()->createFromReview($review, $manager, $this->data());
        $peer = User::factory()->active()->withRole('employe')->create();
        $direction = User::factory()->active()->withRole('direction')->create();

        $this->assertSame(1, $this->plans()->visibleFor($subject)->total());
        $this->assertSame(1, $this->plans()->visibleFor($manager)->total());
        $this->assertSame(1, $this->plans()->visibleFor($direction)->total());
        $this->assertSame(0, $this->plans()->visibleFor($peer)->total());
    }

    /**
     * Cœur de l'AC 12 et de l'AC 43 : la clôture ne produit aucune conséquence sur le compte.
     */
    public function test_ac_12_and_43_closing_a_plan_changes_nothing_on_the_account(): void
    {
        [$manager, $subject, $review] = $this->review();
        $plan = $this->plans()->createFromReview($review, $manager, $this->data());
        DB::table('sessions')->insert([
            'id' => 'session-du-stagiaire',
            'user_id' => $subject->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Tests',
            'payload' => 'vide',
            'last_activity' => CarbonImmutable::now('UTC')->getTimestamp(),
        ]);

        $accountBefore = $subject->fresh()?->getAttributes();
        $rolesBefore = $subject->getRoleNames()->sort()->values()->all();
        $permissionsBefore = $subject->getAllPermissions()->pluck('name')->sort()->values()->all();
        $sessionsBefore = DB::table('sessions')->where('user_id', $subject->getKey())->count();
        $accountAuditsBefore = AuditLog::query()->where('auditable_type', User::class)->count();

        $this->plans()->close($plan, $manager, 'Le rythme attendu est retrouvé.');

        $subject->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertSame($accountBefore, $subject->fresh()?->getAttributes());
        $this->assertSame($rolesBefore, $subject->getRoleNames()->sort()->values()->all());
        $this->assertSame($permissionsBefore, $subject->getAllPermissions()->pluck('name')->sort()->values()->all());
        $this->assertSame($sessionsBefore, DB::table('sessions')->where('user_id', $subject->getKey())->count());
        // Aucune écriture d'audit ne vise le compte : rien n'a été tenté sur `Identity`.
        $this->assertSame($accountAuditsBefore, AuditLog::query()->where('auditable_type', User::class)->count());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $plan->getKey(),
            'action' => 'improvement_plan_closed',
        ]);
    }

    public function test_ac_12_a_plan_is_closed_only_once(): void
    {
        [$manager, , $review] = $this->review();
        $plan = $this->plans()->createFromReview($review, $manager, $this->data());
        $this->plans()->close($plan, $manager, 'Résultat constaté.');

        $this->expectException(ValidationException::class);

        $this->plans()->close($plan->refresh(), $manager, 'Nouvelle tentative.');
    }

    /**
     * AC 13 : tout ce que la personne accompagnée lit relève du soutien, jamais de la sanction.
     */
    public function test_ac_13_every_user_facing_wording_belongs_to_support_not_sanction(): void
    {
        [$manager, , $review] = $this->review();
        $plan = $this->plans()->close(
            $this->plans()->createFromReview($review, $manager, $this->data()),
            $manager,
            'Le rythme attendu est retrouvé.',
        );

        $wording = implode(' ', [
            json_encode($this->plans()->payload($plan), JSON_THROW_ON_ERROR),
            (string) file_get_contents(resource_path('js/Pages/Accountability/ImprovementPlans/Index.vue')),
            (string) file_get_contents(resource_path('js/Pages/Accountability/ImprovementPlans/Show.vue')),
            // Instanciation directe : résoudre un Form Request par le conteneur déclencherait
            // son autorisation, qui n'a rien à voir avec le vocabulaire vérifié ici.
            implode(' ', (new StoreImprovementPlanRequest)->messages()),
            implode(' ', (new CloseImprovementPlanRequest)->messages()),
        ]);

        foreach (['sanction', 'disciplinaire', 'avertissement', 'blâme', 'faute', 'manquement', 'réprimande'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, mb_strtolower($wording));
        }

        foreach (['accompagnement', 'aide fournie', 'actions convenues', 'résultat constaté'] as $expected) {
            $this->assertStringContainsString($expected, mb_strtolower($wording));
        }
    }

    private function plans(): ImprovementPlanService
    {
        return app(ImprovementPlanService::class);
    }

    /** @return array{0: User, 1: User, 2: WeeklyReview} */
    private function review(): array
    {
        $manager = User::factory()->active()->withRole('direction')->create();
        $subject = User::factory()->active()->withRole('employe')->withManager($manager)->create();
        $reviews = app(WeeklyReviewService::class);
        $review = $reviews->open($manager, $subject, CarbonImmutable::parse(self::WEEK_START, 'Africa/Niamey'));
        $reviews->recordObjective($review, Objective::factory()->create(['user_id' => $subject->getKey()]), [
            'result' => 'Le rythme a fléchi en milieu de semaine.',
            'evidence' => 'Rapports quotidiens de la semaine.',
            'status' => ReviewObjectiveStatus::PartiellementAtteint,
            'gap_cause' => 'Une dépendance externe a manqué.',
            'next_action' => 'Convenir un point hebdomadaire.',
        ], $manager);

        return [$manager, $subject, $review->refresh()];
    }

    /**
     * @return array{start_date: string, end_date: string, support_provided: string, actions: list<array{description: string, due_date: string}>}
     */
    private function data(int $days = 10): array
    {
        $start = CarbonImmutable::parse('2026-08-17', 'Africa/Niamey');

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $start->addDays($days - 1)->toDateString(),
            'support_provided' => 'Un point hebdomadaire de trente minutes est fourni.',
            'actions' => [
                ['description' => 'Préparer le point avec deux exemples concrets.', 'due_date' => '2026-08-19'],
                ['description' => 'Partager la difficulté rencontrée dès qu’elle survient.', 'due_date' => '2026-08-24'],
            ],
        ];
    }
}
