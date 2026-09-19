<?php

namespace Tests\Feature\Http;

use App\Models\Finance\FixedCharge;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class FixedChargeHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seed(SettingSeeder::class);
    }

    public function test_ac_9_creation_is_refused_until_the_reserve_impact_has_been_previewed(): void
    {
        $direction = $this->userWithRole('direction');
        $payload = [
            'label' => 'Hébergement local',
            'monthly_amount' => 45_000,
            'is_active' => true,
        ];

        $this->actingAs($direction)
            ->post(route('fixed-charges.store'), $payload)
            ->assertSessionHasErrors(['preview_token']);
        $this->assertDatabaseMissing('fixed_charges', ['label' => 'Hébergement local']);

        $this->actingAs($direction)
            ->post(route('fixed-charges.preview'), $payload)
            ->assertRedirect(route('fixed-charges.index'));
        $preview = session('fixed_charge_preview');
        $this->assertIsArray($preview);
        $this->assertSame(135_000, $preview['impact']['proposed_objective_amount']);

        $this->actingAs($direction)
            ->post(route('fixed-charges.store'), [...$payload, 'preview_token' => $preview['token']])
            ->assertRedirect(route('fixed-charges.index'));
        $this->assertDatabaseHas('fixed_charges', ['label' => 'Hébergement local', 'monthly_amount' => 45_000]);
    }

    public function test_ac_8_9_and_12_update_and_activity_change_require_a_matching_preview(): void
    {
        $finance = $this->userWithRole('finance');
        $charge = FixedCharge::factory()->create(['monthly_amount' => 100_000, 'is_active' => true]);
        $payload = [
            'fixed_charge_id' => $charge->getKey(),
            'label' => $charge->label,
            'monthly_amount' => 125_000,
            'is_active' => false,
        ];

        $this->actingAs($finance)->post(route('fixed-charges.preview'), $payload)->assertRedirect();
        $preview = session('fixed_charge_preview');
        $this->assertIsArray($preview);

        $this->actingAs($finance)->patch(route('fixed-charges.update', $charge), [
            ...$payload,
            'preview_token' => $preview['token'],
        ])->assertRedirect(route('fixed-charges.index'));

        $charge->refresh();
        $this->assertSame(125_000, $charge->monthly_amount);
        $this->assertFalse($charge->is_active);
    }

    public function test_financial_roles_see_the_page_and_every_other_role_is_forbidden_by_direct_url(): void
    {
        foreach (['direction', 'finance'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('fixed-charges.index'))->assertOk();
            $this->resetAuthentication();
        }

        foreach (['super_admin', 'tuteur', 'employe', 'stagiaire'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('fixed-charges.index'))->assertForbidden();
            $this->resetAuthentication();
        }
    }

    public function test_preview_is_rendered_with_explicit_mobile_and_accessibility_contracts(): void
    {
        $direction = $this->userWithRole('direction');
        $payload = ['label' => 'Essai aperçu', 'monthly_amount' => 10_000, 'is_active' => true];
        $this->actingAs($direction)->post(route('fixed-charges.preview'), $payload)->assertRedirect();

        $this->actingAs($direction)->get(route('fixed-charges.index'))->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Finance/FixedCharges/Index')
                ->where('preview.label', 'Essai aperçu')
                ->where('preview.impact.proposed_objective_amount', 30_000)
                ->has('charges', 0),
        );

        $source = (string) file_get_contents(resource_path('js/Pages/Finance/FixedCharges/Index.vue'));
        $this->assertStringContainsString('Impact avant confirmation', $source);
        $this->assertStringContainsString('Aucune charge fixe paramétrée.', $source);
        $this->assertStringContainsString('Incluse dans l’assiette', $source);
        $this->assertStringContainsString('touch-target', $source);
        $this->assertStringContainsString('min-w-0', $source);
        $this->assertStringNotContainsString('overflow-x-', $source);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP story 8.1');

        return $user;
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
