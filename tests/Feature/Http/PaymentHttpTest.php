<?php

namespace Tests\Feature\Http;

use App\Models\Finance\Account;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Invoice;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class PaymentHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ac_28_29_finance_can_record_payment_and_retry_is_idempotent(): void
    {
        $finance = $this->userWithRole('finance');
        [$client, $contract, $invoice, $account] = $this->context();
        $payload = $this->payload($client, $contract, $invoice, $account);

        $this->actingAs($finance)->post(route('payments.store'), $payload)->assertRedirect(route('payments.index'));
        $this->actingAs($finance)->post(route('payments.store'), $payload)->assertRedirect(route('payments.index'));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('receipt_sequences', 1);
    }

    public function test_server_validation_requires_contract_or_project_and_ulid(): void
    {
        $finance = $this->userWithRole('finance');
        [$client, $contract, $invoice, $account] = $this->context();
        $payload = $this->payload($client, $contract, $invoice, $account);
        $payload['contract_id'] = null;
        $payload['project_id'] = null;
        $payload['idempotency_key'] = 'invalide';

        $this->actingAs($finance)->post(route('payments.store'), $payload)->assertSessionHasErrors(['contract_id', 'project_id', 'idempotency_key']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_encaissement_routes_are_limited_to_direction_and_finance(): void
    {
        foreach (['direction', 'finance'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('payments.index'))->assertOk();
            $this->resetAuthentication();
        }
        foreach (['super_admin', 'tuteur', 'employe', 'stagiaire'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('payments.index'))->assertForbidden();
            $this->resetAuthentication();
        }
    }

    public function test_ac_30_32_34_page_exposes_correction_counter_entry_lateness_private_attachment_and_mobile_contracts(): void
    {
        $finance = $this->userWithRole('finance');
        $this->actingAs($finance)->get(route('payments.index'))->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Finance/Payments/Index')->has('payments', 0)->has('idempotencyKey')->has('attachment.allowed_types'),
        );

        $source = (string) file_get_contents(resource_path('js/Pages/Finance/Payments/Index.vue'));
        $this->assertStringContainsString('Corriger avec motif', $source);
        $this->assertStringContainsString('Annuler par contre-écriture', $source);
        $this->assertStringContainsString('plus de 24 h', $source);
        $this->assertStringContainsString('Justificatif privé', $source);
        $this->assertStringContainsString('touch-target', $source);
        $this->assertStringNotContainsString('overflow-x-', $source);
    }

    /** @return array{Client, Contract, Invoice, Account} */
    private function context(): array
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->create(['client_id' => $client->getKey(), 'expected_total_amount' => 1_000_000, 'forecast_profit_amount' => 500_000]);
        $invoice = Invoice::factory()->create(['client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'total_amount' => 1_000_000]);
        $account = Account::factory()->create();

        return [$client, $contract, $invoice, $account];
    }

    /** @return array<string, mixed> */
    private function payload(Client $client, Contract $contract, Invoice $invoice, Account $account): array
    {
        return [
            'client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'project_id' => null,
            'invoice_id' => $invoice->getKey(), 'account_id' => $account->getKey(), 'received_amount' => 100_000,
            'received_at' => now('Africa/Niamey')->format('Y-m-d\\TH:i'), 'payment_mode' => 'especes',
            'reference' => null, 'attachment_ulid' => null, 'idempotency_key' => (string) Str::ulid(),
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test encaissements story 8.1');

        return $user;
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
