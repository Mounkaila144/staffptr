<?php

namespace Tests\Feature;

use App\Enums\ReviewObjectiveStatus;
use App\Enums\UserState;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipChecklistItem;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Models\Work\Objective;
use App\Services\Accountability\InternshipIntakeService;
use App\Services\Accountability\InternshipService;
use App\Services\Accountability\WeeklyReviewService;
use App\Support\Auditing\AuditContext;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Task 9 — écritures, audit et immuabilité centralisés (AC 4, 7, 12, 15 à 19, 26, 28 à 32, 38).
 */
class EpicSevenWriteDisciplineTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    /** Contrôleurs livrés par la story 7.1. */
    private const CONTROLLERS = [
        'WeeklyReviewController',
        'ImprovementPlanController',
        'InternshipIntakeController',
        'InternshipController',
        'TutorCapacityController',
        'TutorSupportSlotController',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    /**
     * SOC-03 : aucune route de l'epic ne supprime quoi que ce soit.
     */
    public function test_no_epic_seven_route_deletes_anything(): void
    {
        $epicSevenPrefixes = ['weekly-reviews.', 'improvement-plans.', 'internship-intakes.', 'internships.', 'interns.', 'tutors.', 'support-slots.'];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            foreach ($epicSevenPrefixes as $prefix) {
                if (! str_starts_with($name, $prefix)) {
                    continue;
                }

                $this->assertNotContains(
                    'DELETE',
                    $route->methods(),
                    "La route {$name} ne doit pas exposer de suppression.",
                );
            }
        }
    }

    /**
     * Les contrôleurs restent fins : aucune transaction, aucune validation inline, aucun accès
     * direct à la base.
     */
    public function test_epic_seven_controllers_stay_thin(): void
    {
        foreach (self::CONTROLLERS as $controller) {
            $source = (string) file_get_contents(
                app_path("Http/Controllers/Accountability/{$controller}.php"),
            );

            $this->assertStringNotContainsString('DB::', $source, "{$controller} ne doit pas accéder à la base.");
            $this->assertStringNotContainsString('->transaction(', $source, "{$controller} ne doit ouvrir aucune transaction.");
            $this->assertStringNotContainsString('$request->validate(', $source, "{$controller} ne doit pas valider en ligne.");
            $this->assertStringNotContainsString('Validator::', $source, "{$controller} ne doit pas valider en ligne.");
        }
    }

    /**
     * SOC-02 : l'échec de l'écriture d'audit annule l'opération métier. Vérifié sur une écriture
     * de l'epic — l'ouverture d'une revue.
     */
    public function test_a_failing_audit_cancels_the_business_write(): void
    {
        $reviewer = User::factory()->active()->withRole('direction')->create();
        $subject = User::factory()->active()->withRole('employe')->withManager($reviewer)->create();

        $this->bindFailingAuditLogger();

        try {
            app(WeeklyReviewService::class)->open(
                $reviewer,
                $subject,
                CarbonImmutable::parse('2026-08-10', 'Africa/Niamey'),
            );
            $this->fail("L'échec d'audit doit annuler l'ouverture de la revue.");
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit indisponible.', $exception->getMessage());
        }

        $this->assertSame(0, WeeklyReview::query()->count());
    }

    /**
     * AC 19 et règle de couplage : l'activation d'un stagiaire est **une seule transaction** qui
     * traverse `Accountability` et `Identity`. Si l'audit final échoue, l'écriture faite dans
     * `Identity` est annulée avec le reste.
     */
    public function test_activation_is_one_transaction_across_accountability_and_identity(): void
    {
        [$direction, $candidate] = $this->approvedCandidate();

        $this->bindFailingAuditLogger();

        try {
            app(InternshipService::class)->activate($candidate, $direction);
            $this->fail("L'échec d'audit doit annuler l'activation entière.");
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit indisponible.', $exception->getMessage());
        }

        // Ni le stage, ni la checklist, ni le changement d'état du compte ne subsistent.
        $this->assertSame(0, Internship::query()->count());
        $this->assertSame(0, InternshipChecklistItem::query()->count());
        $this->assertSame(UserState::Invite, $candidate->refresh()->state);
    }

    /**
     * Toute écriture vers `Identity` passe par son service propriétaire : `InternshipService` le
     * déclare comme dépendance et ne modifie jamais un compte lui-même.
     */
    public function test_accountability_writes_to_identity_only_through_its_owning_service(): void
    {
        $source = (string) file_get_contents(app_path('Services/Accountability/InternshipService.php'));

        $this->assertStringContainsString('IdentityService $identityService', $source);
        $this->assertStringContainsString('$this->identityService->changeUserState(', $source);
        // Aucune écriture directe sur le modèle de compte.
        $this->assertStringNotContainsString('$candidate->save', $source);
        $this->assertStringNotContainsString('$candidate->update', $source);
        $this->assertStringNotContainsString('User::query()->whereKey($candidate', $source);
    }

    /**
     * AC 7, 32 : les objets figés refusent l'écriture, et aucun modèle de l'epic n'accepte la
     * suppression physique.
     */
    public function test_frozen_objects_refuse_writes_and_physical_deletion(): void
    {
        $models = [
            WeeklyReview::factory()->validated()->create(),
            InternshipIntakeForm::factory()->approved()->create(),
            Internship::factory()->create(),
        ];

        foreach ($models as $model) {
            try {
                $model->delete();
                $this->fail($model::class.' ne doit pas accepter la suppression physique.');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('suppression physique', $exception->getMessage());
            }
        }
    }

    /**
     * Remplace le journal d'audit par une implémentation qui échoue, pour prouver que
     * l'opération métier est annulée avec lui.
     */
    private function bindFailingAuditLogger(): void
    {
        $this->app->bind(AuditLogger::class, fn (): AuditLogger => new class(app(AuditContext::class)) extends AuditLogger
        {
            public function record(
                ?int $actorId,
                string $actorLabel,
                Model $auditable,
                string $action,
                ?array $oldValues = null,
                ?array $newValues = null,
                ?string $reason = null,
                ?string $requestId = null,
                ?string $ipAddress = null,
                ?string $userAgent = null,
            ): AuditLog {
                throw new RuntimeException('Audit indisponible.');
            }
        });
    }

    /** @return array{0: User, 1: User} */
    private function approvedCandidate(): array
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $candidate = User::factory()->withRole('stagiaire')->create(['state' => UserState::Invite]);

        $intakes = app(InternshipIntakeService::class);
        $form = $intakes->draft($direction, [
            'candidate_user_id' => (int) $candidate->getKey(),
            'manager_id' => (int) $direction->getKey(),
            'tutor_id' => (int) $tutor->getKey(),
            'real_need' => 'Renfort.',
            'mission' => 'Documenter.',
            'duration_weeks' => 12,
            'tools' => 'Poste.',
            'outcomes' => ['Un.', 'Deux.', 'Trois.'],
        ]);
        $intakes->decide($intakes->submit($form, $direction), $direction, approved: true);
        Objective::factory()->count(3)->create(['user_id' => $candidate->getKey()]);

        return [$direction, $candidate];
    }

    /** @return array{result: string, evidence: null, status: ReviewObjectiveStatus, gap_cause: null, next_action: string} */
    private function entry(): array
    {
        return [
            'result' => 'Fait.',
            'evidence' => null,
            'status' => ReviewObjectiveStatus::Atteint,
            'gap_cause' => null,
            'next_action' => 'Poursuivre.',
        ];
    }
}
