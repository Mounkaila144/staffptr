<?php

namespace Tests\Feature\Http;

use App\Models\Finance\Client;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class ClientContractHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ac_13_financial_roles_manage_clients_and_phone_is_normalized_before_uniqueness(): void
    {
        $finance = $this->userWithRole('finance');
        $payload = ['name' => 'Télécom Niger', 'phone' => '90 00 00 02', 'contact' => 'Moussa', 'notes' => null, 'is_active' => true];

        $this->actingAs($finance)->post(route('clients.store'), $payload)->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', ['name' => 'Télécom Niger', 'phone' => '+22790000002']);
        $this->actingAs($finance)->post(route('clients.store'), [...$payload, 'name' => 'Doublon'])->assertSessionHasErrors('phone');
    }

    public function test_ac_14_to_20_contract_form_creates_employee_contributor_and_associate_execution(): void
    {
        $direction = $this->userWithRole('direction');
        $employee = User::factory()->active()->employee()->create();
        $associate = $this->userWithRole('direction');
        $client = Client::factory()->create();
        $payload = [
            'client_id' => $client->getKey(), 'project_id' => null, 'reference' => 'CTR-HTTP-001',
            'title' => 'Déploiement terrain', 'expected_total_amount' => 2_000_000,
            'forecast_profit_amount' => 1_000_000, 'contributor_id' => $employee->getKey(),
            'has_execution' => true, 'executor_ids' => [$associate->getKey()],
            'starts_on' => '2026-08-01', 'ends_on' => '2026-08-31',
        ];

        $this->actingAs($direction)->post(route('contracts.store'), $payload)->assertRedirect(route('contracts.index'));
        $this->assertDatabaseHas('contracts', ['reference' => 'CTR-HTTP-001', 'contributor_id' => $employee->getKey()]);
        $this->assertDatabaseHas('contract_executors', ['user_id' => $associate->getKey(), 'position' => 1, 'is_active' => true]);
    }

    public function test_financial_pages_are_for_direction_and_finance_only_even_by_direct_url(): void
    {
        foreach (['direction', 'finance'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('clients.index'))->assertOk();
            $this->actingAs($user)->get(route('contracts.index'))->assertOk();
            $this->resetAuthentication();
        }
        foreach (['super_admin', 'tuteur', 'employe', 'stagiaire'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('clients.index'))->assertForbidden();
            $this->actingAs($user)->get(route('contracts.index'))->assertForbidden();
            $this->resetAuthentication();
        }
    }

    public function test_ac_43_to_50_contract_page_exposes_method_parts_variance_and_mobile_contracts(): void
    {
        $direction = $this->userWithRole('direction');
        $this->actingAs($direction)->get(route('contracts.index'))->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Finance/Contracts/Index')->has('contracts', 0)->has('clients')->has('contributors')->has('executors'),
        );

        $source = (string) file_get_contents(resource_path('js/Pages/Finance/Contracts/Index.vue'));
        $this->assertStringContainsString('Méthode :', $source);
        $this->assertStringContainsString('Proposition de régularisation à la clôture', $source);
        $this->assertStringContainsString('Associés exécutants', $source);
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
