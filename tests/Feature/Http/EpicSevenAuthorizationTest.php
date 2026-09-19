<?php

namespace Tests\Feature\Http;

use App\Enums\ReviewObjectiveStatus;
use App\Enums\UserState;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Services\Accountability\ImprovementPlanService;
use App\Services\Accountability\WeeklyReviewService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Route;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Task 8 — campagne d'autorisation de l'Epic 7 au niveau **objet**.
 *
 * La matrice `config/authorization-matrix.php` couvre le niveau rôle × route ; ce test couvre le
 * quatrième niveau, celui que la matrice ne peut pas exprimer : la portée de visibilité par objet.
 * Un compte porteur de la bonne permission mais étranger à l'objet doit être refusé (AC 1, 5, 8,
 * 11, 15, 16, 20, 24, 28, 31, 37).
 */
class EpicSevenAuthorizationTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    /** Routes protégées introduites par la story 7.1. */
    private const EPIC_SEVEN_ROUTES = [
        'weekly-reviews.index', 'weekly-reviews.show', 'weekly-reviews.comments.store',
        'weekly-reviews.validate', 'weekly-reviews.store', 'weekly-reviews.objectives.store',
        'weekly-reviews.submit',
        'improvement-plans.index', 'improvement-plans.show', 'improvement-plans.store',
        'improvement-plans.close',
        'internship-intakes.index', 'internship-intakes.show', 'internship-intakes.store',
        'internship-intakes.submit', 'internship-intakes.decide', 'interns.activate',
        'tutors.capacity', 'internships.index', 'internships.show', 'internships.plan.save',
        'internships.evaluations.store', 'internships.evaluations.validate', 'internships.exit',
        'support-slots.index', 'support-slots.store',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    /**
     * Chaque route protégée de l'epic est déclarée dans la matrice, avec une permission métier.
     */
    public function test_every_epic_seven_route_is_declared_in_the_authorization_matrix(): void
    {
        /** @var array<string, array<string, mixed>> $matrix */
        $matrix = config('authorization-matrix.routes');

        foreach (self::EPIC_SEVEN_ROUTES as $routeName) {
            $this->assertNotNull(Route::getRoutes()->getByName($routeName), "Route {$routeName} absente.");
            $this->assertArrayHasKey($routeName, $matrix, "Route {$routeName} non déclarée dans la matrice.");
            $this->assertNotEmpty($matrix[$routeName]['permission'], "Route {$routeName} sans permission.");
        }
    }

    /**
     * PERM-03 : `super_admin` ne reçoit aucune permission métier de l'Epic 7, et se voit refuser
     * chaque route de l'epic.
     */
    public function test_super_admin_holds_no_epic_seven_business_permission(): void
    {
        /** @var array<string, list<string>> $roles */
        $roles = config('permission-catalog.roles');
        $epicSevenPermissions = ['revue_hebdomadaire.consulter', 'revue_hebdomadaire.gerer', 'stagiaire.consulter', 'stagiaire.gerer'];

        $this->assertSame([], array_values(array_intersect($epicSevenPermissions, $roles['super_admin'])));

        /** @var array<string, array<string, mixed>> $matrix */
        $matrix = config('authorization-matrix.routes');

        foreach (self::EPIC_SEVEN_ROUTES as $routeName) {
            $this->assertSame(
                403,
                $matrix[$routeName]['statuses']['super_admin'],
                "super_admin doit être refusé sur {$routeName}.",
            );
        }
    }

    /**
     * AC 1, 5 et 11 : un pair porteur de la permission de consultation reste refusé sur la revue
     * et le plan d'accompagnement d'autrui, par URL directe.
     */
    public function test_a_peer_with_the_permission_is_still_refused_on_someone_elses_review(): void
    {
        [$direction, $subject, $review] = $this->review();
        $plan = app(ImprovementPlanService::class)->createFromReview($review, $direction, $this->planData());

        // `employe` détient `revue_hebdomadaire.consulter` : le refus vient donc bien de la Policy.
        $peer = User::factory()->active()->withRole('employe')->create();
        $this->assertTrue($peer->can('revue_hebdomadaire.consulter'));

        $this->flushSession();
        $this->actingAs($peer)->get(route('weekly-reviews.show', $review))->assertForbidden();
        $this->actingAs($peer)->get(route('improvement-plans.show', $plan))->assertForbidden();

        // La personne concernée, elle, accède aux deux.
        $this->flushSession();
        $this->actingAs($subject)->get(route('weekly-reviews.show', $review))->assertOk();
        $this->actingAs($subject)->get(route('improvement-plans.show', $plan))->assertOk();
    }

    /**
     * AC 15 et 16 : un tuteur rédige et soumet une fiche d'entrée mais ne décide pas et n'active
     * pas — la décision et l'activation appartiennent à `direction`.
     */
    public function test_a_tutor_prepares_the_intake_but_direction_alone_decides_and_activates(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $candidate = User::factory()->withRole('stagiaire')->create(['state' => UserState::Invite]);

        $this->actingAs($tutor)
            ->post(route('internship-intakes.store'), $this->intakePayload($candidate, $tutor, $direction))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $form = InternshipIntakeForm::query()->firstOrFail();

        $this->actingAs($tutor)->patch(route('internship-intakes.submit', $form))->assertRedirect();
        // Le tuteur ne décide pas.
        $this->actingAs($tutor)
            ->patch(route('internship-intakes.decide', $form), ['approved' => true])
            ->assertForbidden();

        $this->flushSession();
        $this->actingAs($direction)
            ->patch(route('internship-intakes.decide', $form), ['approved' => true])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Objective::factory()->count(3)->create(['user_id' => $candidate->getKey()]);

        // Le tuteur n'active pas non plus.
        $this->flushSession();
        $this->actingAs($tutor)->post(route('interns.activate', $candidate))->assertForbidden();
        $this->assertSame(UserState::Invite, $candidate->refresh()->state);

        $this->flushSession();
        $this->actingAs($direction)->post(route('interns.activate', $candidate))->assertRedirect();
        $this->assertSame(UserState::Actif, $candidate->refresh()->state);
    }

    /**
     * AC 24, 28 et 31 : un tuteur n'accède qu'aux dossiers de ses propres stagiaires.
     */
    public function test_a_tutor_reaches_only_their_own_interns_dossiers(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $otherTutor = User::factory()->active()->withRole('tuteur')->create();
        $mine = Internship::factory()->forTutor($tutor)->create();
        $theirs = Internship::factory()->forTutor($otherTutor)->create();

        $this->actingAs($tutor)->get(route('internships.show', $mine))->assertOk();
        $this->actingAs($tutor)->get(route('internships.show', $theirs))->assertForbidden();
        $this->actingAs($tutor)
            ->post(route('internships.evaluations.store', $theirs), [
                'type' => 'hebdomadaire',
                'week_start_date' => '2026-08-10',
                'observed_progress' => 'Intrusion.',
            ])
            ->assertForbidden();

        // `direction` voit les deux.
        $this->flushSession();
        $direction = User::factory()->active()->withRole('direction')->create();
        $this->actingAs($direction)->get(route('internships.show', $mine))->assertOk();
        $this->actingAs($direction)->get(route('internships.show', $theirs))->assertOk();
    }

    /**
     * AC 20 : l'écran de charge des tuteurs est réservé à qui gère les stagiaires.
     */
    public function test_the_tutor_capacity_screen_is_reserved_to_intern_managers(): void
    {
        foreach (['direction', 'tuteur'] as $allowed) {
            $this->flushSession();
            $this->actingAs(User::factory()->active()->withRole($allowed)->create())
                ->get(route('tutors.capacity'))
                ->assertOk();
        }

        foreach (['employe', 'finance', 'stagiaire'] as $refused) {
            $this->flushSession();
            $this->actingAs(User::factory()->active()->withRole($refused)->create())
                ->get(route('tutors.capacity'))
                ->assertForbidden();
        }
    }

    /**
     * AC 8 et 37 : un compte non authentifié est renvoyé à la connexion, jamais servi
     * partiellement.
     */
    public function test_an_unauthenticated_visitor_is_redirected_never_served(): void
    {
        [, , $review] = $this->review();

        $this->get(route('weekly-reviews.show', $review))->assertRedirect(route('login'));
        $this->get(route('internships.index'))->assertRedirect(route('login'));
        $this->get(route('support-slots.index'))->assertRedirect(route('login'));
    }

    /** @return array{0: User, 1: User, 2: WeeklyReview} */
    private function review(): array
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $subject = User::factory()->active()->withRole('employe')->withManager($direction)->create();
        $reviews = app(WeeklyReviewService::class);
        $review = $reviews->open($direction, $subject, CarbonImmutable::parse('2026-08-10', 'Africa/Niamey'));
        $reviews->recordObjective($review, Objective::factory()->create(['user_id' => $subject->getKey()]), [
            'result' => 'Fait.',
            'evidence' => null,
            'status' => ReviewObjectiveStatus::Atteint,
            'gap_cause' => null,
            'next_action' => 'Poursuivre.',
        ], $direction);

        return [$direction, $subject, $review->refresh()];
    }

    /**
     * @return array{start_date: string, end_date: string, support_provided: string, actions: list<array{description: string, due_date: string}>}
     */
    private function planData(): array
    {
        return [
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-26',
            'support_provided' => 'Un point hebdomadaire.',
            'actions' => [['description' => 'Préparer le point.', 'due_date' => '2026-08-19']],
        ];
    }

    /** @return array<string, mixed> */
    private function intakePayload(User $candidate, User $tutor, User $manager): array
    {
        return [
            'candidate_user_id' => $candidate->getKey(),
            'manager_id' => $manager->getKey(),
            'tutor_id' => $tutor->getKey(),
            'real_need' => 'Renfort documentation.',
            'mission' => 'Rédiger les procédures.',
            'duration_weeks' => 12,
            'tools' => 'Poste de travail.',
            'outcomes' => ['Un résultat.', 'Un deuxième.', 'Un troisième.'],
        ];
    }
}
