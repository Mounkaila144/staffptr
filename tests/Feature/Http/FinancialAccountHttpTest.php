<?php

namespace Tests\Feature\Http;

use App\Models\Finance\Account;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class FinancialAccountHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ac_1_2_and_4_direction_and_finance_can_create_and_view_a_calculated_account(): void
    {
        $direction = $this->userWithRole('direction');
        $finance = $this->userWithRole('finance');

        $this->actingAs($direction)->post(route('financial-accounts.store'), [
            'type' => 'caisse',
            'label' => 'Caisse direction',
            'opening_balance_amount' => 75_000,
            'opening_balance_date' => '2026-08-17',
            'current_balance_amount' => 99_999_999,
        ])->assertRedirect(route('financial-accounts.index'));
        $account = Account::query()->where('label', 'Caisse direction')->sole();

        foreach ([$direction, $finance] as $user) {
            $this->actingAs($user)->get(route('financial-accounts.index'))->assertOk()->assertInertia(
                fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('Finance/Accounts/Index')
                    ->where('accounts.0.id', $account->getKey())
                    ->where('accounts.0.current_balance_amount', 75_000)
                    ->where('accounts.0.current_balance', '75 000 FCFA')
                    ->missing('accounts.0.current_balance_input'),
            );

            $this->resetAuthentication();
        }

        $this->actingAs($finance)->post(route('financial-accounts.store'), [
            'type' => 'mobile_money',
            'label' => 'Mobile finance',
            'opening_balance_amount' => 0,
            'opening_balance_date' => '2026-08-17',
        ])->assertRedirect(route('financial-accounts.index'));
        $this->assertDatabaseHas('accounts', ['label' => 'Mobile finance']);
    }

    public function test_ac_4_direct_url_access_is_forbidden_to_every_other_role(): void
    {
        foreach (['super_admin', 'tuteur', 'employe', 'stagiaire'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('financial-accounts.index'))->assertForbidden();
            $this->actingAs($user)->post(route('financial-accounts.store'), [
                'type' => 'caisse',
                'label' => "Interdit {$role}",
                'opening_balance_amount' => 0,
                'opening_balance_date' => '2026-08-17',
            ])->assertForbidden();

            $this->resetAuthentication();
        }
    }

    public function test_ac_1_creation_validation_rejects_invalid_type_negative_balance_future_date_and_duplicate_label(): void
    {
        $direction = $this->userWithRole('direction');
        Account::factory()->create(['label' => 'Caisse principale']);

        $this->actingAs($direction)->post(route('financial-accounts.store'), [
            'type' => 'crypto',
            'label' => 'Caisse principale',
            'opening_balance_amount' => -1,
            'opening_balance_date' => '2099-01-01',
        ])->assertSessionHasErrors(['type', 'label', 'opening_balance_amount', 'opening_balance_date']);
    }

    public function test_ac_6_deactivation_requires_a_reason_and_never_exposes_a_delete_route(): void
    {
        $finance = $this->userWithRole('finance');
        $account = Account::factory()->create();

        $this->actingAs($finance)
            ->patch(route('financial-accounts.deactivate', $account), ['reason' => ''])
            ->assertSessionHasErrors(['reason']);
        $this->actingAs($finance)
            ->patch(route('financial-accounts.deactivate', $account), ['reason' => 'Compte remplacé.'])
            ->assertRedirect(route('financial-accounts.index'));

        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())
            ->contains(static fn ($route): bool => $route->getName() === 'financial-accounts.destroy'));
    }

    public function test_ac_4_mobile_page_has_explicit_empty_state_and_no_current_balance_input(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Finance/Accounts/Index.vue'));

        $this->assertStringContainsString('Aucun compte financier.', $source);
        $this->assertStringContainsString('Solde courant calculé', $source);
        $this->assertStringContainsString('touch-target', $source);
        $this->assertStringContainsString('min-w-0', $source);
        $this->assertStringNotContainsString('v-model="creationForm.current_balance', $source);
        $this->assertStringNotContainsString('overflow-x-', $source);
        $this->assertStringNotContainsString('http://', $source);
        $this->assertStringNotContainsString('https://', $source);
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
