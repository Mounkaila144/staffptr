<?php

namespace Tests\Feature\Http;

use App\Enums\CorrectionPlanState;
use App\Models\Finance\CorrectionPlan;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Tests\Support\IdentityTestCase;

/**
 * Story 9.1, Tasks 4 et 8 — surface HTTP du plan correctif (AC 14 à 18, AC 22, AC 43).
 */
class CorrectionPlanHttpTest extends IdentityTestCase
{
    private CarbonImmutable $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->month = CarbonImmutable::now('Africa/Niamey')->startOfMonth();
    }

    /** AC 15 — la consultation est ouverte à `direction` et `finance`, à personne d'autre. */
    public function test_the_plan_list_is_reserved_to_direction_and_finance(): void
    {
        foreach (['direction', 'finance'] as $role) {
            session()->flush();
            $this->actingAs($this->userWithRole($role))
                ->get('/finances/plans-correctifs')
                ->assertOk();
        }

        foreach (['super_admin', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            $this->actingAs($this->userWithRole($role))
                ->get('/finances/plans-correctifs')
                ->assertForbidden();
        }
    }

    /** AC 14 — seule la direction enregistre un plan ; `finance` le consulte sans l'écrire. */
    public function test_ac_14_only_direction_can_record_a_plan(): void
    {
        session()->flush();
        $this->actingAs($this->userWithRole('finance'))
            ->post('/finances/plans-correctifs', $this->payload())
            ->assertForbidden();

        session()->flush();
        $this->actingAs($this->userWithRole('direction'))
            ->post('/finances/plans-correctifs', $this->payload())
            ->assertRedirect();

        $this->assertSame(1, CorrectionPlan::query()->count());
    }

    /** AC 14 — un plan amputé d'un de ses cinq champs est refusé, champ par champ. */
    public function test_ac_14_an_incomplete_plan_is_refused_field_by_field(): void
    {
        $direction = $this->userWithRole('direction');

        foreach (['finding', 'actions', 'responsibles', 'due_on', 'expected_result'] as $field) {
            $payload = $this->payload();
            unset($payload[$field]);

            session()->flush();
            $this->actingAs($direction)
                ->post('/finances/plans-correctifs', $payload)
                ->assertSessionHasErrors($field);
        }

        $this->assertSame(0, CorrectionPlan::query()->count());
    }

    /** AC 18 — la validation passe par la route dédiée et fige le plan. */
    public function test_ac_18_validating_through_http_freezes_the_plan(): void
    {
        $direction = $this->userWithRole('direction');
        $plan = CorrectionPlan::factory()->create([
            'month' => $this->month->toDateString(),
            'created_by' => $direction->getKey(),
        ]);

        $this->actingAs($direction)
            ->patch("/finances/plans-correctifs/{$plan->getKey()}/valider")
            ->assertRedirect();

        $this->assertSame(CorrectionPlanState::Valide, $plan->fresh()->state);
    }

    /** AC 17 — la révision d'un plan validé crée une nouvelle version par la route dédiée. */
    public function test_ac_17_revising_through_http_creates_a_new_version(): void
    {
        $direction = $this->userWithRole('direction');
        $plan = CorrectionPlan::factory()->validated()->create([
            'month' => $this->month->toDateString(),
            'created_by' => $direction->getKey(),
        ]);

        $this->actingAs($direction)
            ->post("/finances/plans-correctifs/{$plan->getKey()}/reviser", [
                ...$this->payload(),
                'revision_reason' => 'Le premier plan n’a pas produit son effet.',
            ])
            ->assertRedirect();

        $this->assertSame(2, CorrectionPlan::query()->count());
        $this->assertSame(CorrectionPlanState::Valide, $plan->fresh()->state, "L'original ne doit pas changer.");
    }

    /** AC 17 — un plan encore en brouillon ne se révise pas : la Policy le refuse. */
    public function test_ac_17_a_draft_plan_cannot_be_revised_through_http(): void
    {
        $direction = $this->userWithRole('direction');
        $plan = CorrectionPlan::factory()->create([
            'month' => $this->month->toDateString(),
            'created_by' => $direction->getKey(),
        ]);

        $this->actingAs($direction)
            ->post("/finances/plans-correctifs/{$plan->getKey()}/reviser", [
                ...$this->payload(),
                'revision_reason' => 'Révision prématurée.',
            ])
            ->assertForbidden();
    }

    /** AC 15 — l'écran expose l'historique complet des versions, aucune n'étant masquée. */
    public function test_ac_15_the_screen_exposes_every_version(): void
    {
        $direction = $this->userWithRole('direction');
        $first = CorrectionPlan::factory()->validated()->create([
            'month' => $this->month->toDateString(),
            'version' => 1,
            'created_by' => $direction->getKey(),
        ]);
        CorrectionPlan::factory()->create([
            'month' => $this->month->toDateString(),
            'version' => 2,
            'previous_id' => $first->getKey(),
            'created_by' => $direction->getKey(),
        ]);

        $this->actingAs($direction)
            ->get('/finances/plans-correctifs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/CorrectionPlans/Index')
                ->has('plans', 2)
                ->where('plans.0.version', 1)
                ->where('plans.1.version', 2)
                ->has('alert.level_label'));
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->active()->withRole($role)->create();
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return [
            'month' => $this->month->toDateString(),
            'finding' => 'Les encaissements sont restés sous l’assiette.',
            'actions' => 'Relancer les factures échues.',
            'responsibles' => 'Direction et responsable financier',
            'due_on' => $this->month->addDays(14)->toDateString(),
            'expected_result' => 'Retour au niveau de l’assiette.',
        ];
    }
}
