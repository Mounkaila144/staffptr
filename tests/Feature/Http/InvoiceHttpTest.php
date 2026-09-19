<?php

namespace Tests\Feature\Http;

use App\Enums\InvoiceState;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Invoice;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class InvoiceHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ac_21_22_creation_never_accepts_a_number_or_editable_state(): void
    {
        $finance = $this->userWithRole('finance');
        $client = Client::factory()->create();
        $contract = Contract::factory()->create(['client_id' => $client->getKey()]);
        $payload = [
            'client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'total_amount' => 200_000,
            'issued_on' => '2026-08-01', 'due_on' => '2026-08-31', 'number' => 'IMPOSE', 'state' => 'payee',
        ];

        $this->actingAs($finance)->post(route('invoices.store'), $payload)->assertRedirect(route('invoices.index'));
        $invoice = Invoice::query()->sole();
        $this->assertNotSame('IMPOSE', $invoice->number);
        $this->assertSame(InvoiceState::Impayee, $invoice->state);
    }

    public function test_ac_25_cancellation_without_a_valid_reason_is_refused(): void
    {
        $invoice = $this->invoice();
        $finance = $this->userWithRole('finance');

        $this->actingAs($finance)->patch(route('invoices.cancel', $invoice), ['reason' => 'court'])->assertSessionHasErrors('reason');
        $this->assertSame(InvoiceState::Impayee, $invoice->refresh()->state);
    }

    public function test_financial_roles_only_can_reach_invoice_routes_by_direct_url(): void
    {
        foreach (['direction', 'finance'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('invoices.index'))->assertOk();
            $this->resetAuthentication();
        }
        foreach (['super_admin', 'tuteur', 'employe', 'stagiaire'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('invoices.index'))->assertForbidden();
            $this->resetAuthentication();
        }
    }

    public function test_ac_23_24_26_27_page_has_receivable_filter_empty_and_mvp_contracts(): void
    {
        $direction = $this->userWithRole('direction');
        $this->actingAs($direction)->get(route('invoices.index'))->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Finance/Invoices/Index')->has('invoices', 0)->has('receivables', 0)->has('filters'),
        );

        $source = (string) file_get_contents(resource_path('js/Pages/Finance/Invoices/Index.vue'));
        $this->assertStringContainsString('Aucune créance échue.', $source);
        $this->assertStringContainsString('Aucun PDF ni aucune relance', $source);
        $this->assertStringContainsString('Les statuts sont déduits', $source);
        $this->assertStringContainsString('touch-target', $source);
        $this->assertStringNotContainsString('overflow-x-', $source);
    }

    private function invoice(): Invoice
    {
        $client = Client::factory()->create();

        return Invoice::factory()->create([
            'client_id' => $client->getKey(), 'contract_id' => Contract::factory()->create(['client_id' => $client->getKey()])->getKey(),
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test factures story 8.1');

        return $user;
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
