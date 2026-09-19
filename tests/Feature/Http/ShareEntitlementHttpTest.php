<?php

namespace Tests\Feature\Http;

use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Payment;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class ShareEntitlementHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seed(ExpenseCategorySeeder::class);
    }

    public function test_contra_05_employee_cannot_read_another_beneficiary_line_by_direct_url(): void
    {
        $employee = $this->roleUser('employe');
        $own = $this->share($employee);
        $other = $this->share(User::factory()->active()->create());

        $this->actingAs($employee)->get(route('shares.show', $own))->assertOk();
        $this->actingAs($employee)->get(route('shares.show', $other))->assertForbidden();
        $this->actingAs($employee)->get(route('shares.index'))->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Finance/Shares/Index')->has('shares', 1)->where('shares.0.id', $own->getKey())->where('ownScope', true),
        );
    }

    public function test_employee_can_request_only_own_share_payment(): void
    {
        $employee = $this->roleUser('employe');
        $own = $this->share($employee);
        $other = $this->share(User::factory()->active()->create());

        $this->actingAs($employee)->post(route('shares.request-payment', $other))->assertForbidden();
        $this->actingAs($employee)->post(route('shares.request-payment', $own))->assertRedirect();
        $this->assertDatabaseHas('expenses', ['share_entitlement_id' => $own->getKey(), 'beneficiary_user_id' => $employee->getKey()]);
    }

    public function test_share_register_roles_follow_global_or_own_scope(): void
    {
        foreach (['direction', 'finance', 'employe'] as $role) {
            $this->actingAs($this->roleUser($role))->get(route('shares.index'))->assertOk();
            $this->resetAuthentication();
        }
        foreach (['super_admin', 'tuteur', 'stagiaire'] as $role) {
            $this->actingAs($this->roleUser($role))->get(route('shares.index'))->assertForbidden();
            $this->resetAuthentication();
        }
    }

    public function test_ac_47_48_57_page_exposes_paid_remaining_method_origin_and_confidentiality(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Finance/Shares/Index.vue'));
        $this->assertStringContainsString('Déjà versé', $source);
        $this->assertStringContainsString('Restant à verser', $source);
        $this->assertStringContainsString('Méthode :', $source);
        $this->assertStringContainsString('uniquement votre propre part', $source);
        $this->assertStringContainsString('min-w-0', $source);
        $this->assertStringNotContainsString('overflow-x-', $source);
    }

    private function share(User $beneficiary): ShareEntitlement
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->create(['client_id' => $client->getKey()]);
        $payment = Payment::factory()->create(['client_id' => $client->getKey(), 'contract_id' => $contract->getKey()]);

        return ShareEntitlement::factory()->create([
            'contract_id' => $contract->getKey(), 'payment_id' => $payment->getKey(),
            'beneficiary_id' => $beneficiary->getKey(), 'beneficiary_key' => 'user:'.$beneficiary->getKey(),
        ]);
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP parts story 8.1');

        return $user;
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
