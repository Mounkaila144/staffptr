<?php

namespace Tests\Feature;

use App\Enums\ContractState;
use App\Enums\ExpenseState;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Expense;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\ClientService;
use App\Services\Finance\ContractService;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

class ContractLifecycleTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ac_13_clients_are_normalized_managed_and_audited_without_physical_deletion(): void
    {
        $actor = User::factory()->active()->create();
        $service = app(ClientService::class);
        $client = $service->create([
            'name' => '  Sahel Industrie ', 'phone' => '90 00 00 01', 'contact' => ' Amina ',
            'notes' => '', 'is_active' => true,
        ], $actor);

        $this->assertSame('Sahel Industrie', $client->name);
        $this->assertSame('+22790000001', $client->phone);
        $this->assertNull($client->notes);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Client::class, 'auditable_id' => $client->getKey(), 'action' => 'client_created',
        ]);
        $this->expectException(\LogicException::class);
        $client->delete();
    }

    public function test_ac_14_15_and_43_contract_accepts_optional_project_and_employee_contributor(): void
    {
        $actor = User::factory()->active()->create();
        $employee = User::factory()->active()->employee()->create();
        $associate = $this->associate();
        $contract = app(ContractService::class)->create($this->payload([
            'contributor_id' => $employee->getKey(), 'has_execution' => true, 'executor_ids' => [$associate->getKey()],
        ]), $actor);
        $summary = app(ContractService::class)->summary($contract);

        $this->assertNull($contract->project_id);
        $this->assertSame([100_000, 600_000, 300_000], array_column($summary['shares'], 'share_amount'));
        $this->assertSame([$associate->getKey()], $summary['executor_ids']);
        $this->assertSame(1_000_000, array_sum(array_column($summary['shares'], 'share_amount')));
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Contract::class, 'auditable_id' => $contract->getKey(), 'action' => 'contract_created',
        ]);
    }

    public function test_ac_15_contract_without_contributor_assigns_one_hundred_percent_to_ptr_niger(): void
    {
        $contract = app(ContractService::class)->create($this->payload([
            'contributor_id' => null, 'has_execution' => true, 'executor_ids' => [$this->associate()->getKey()],
        ]), User::factory()->active()->create());
        $summary = app(ContractService::class)->summary($contract);

        $this->assertCount(1, $summary['shares']);
        $this->assertSame('PTR Niger', $summary['shares'][0]['beneficiary_name']);
        $this->assertSame(1_000_000, $summary['shares'][0]['share_amount']);
        $this->assertSame([], $summary['executor_ids']);
    }

    public function test_ac_16_and_44_only_active_associates_can_execute(): void
    {
        $employee = User::factory()->active()->employee()->create();

        try {
            app(ContractService::class)->create($this->payload([
                'contributor_id' => $employee->getKey(), 'has_execution' => true, 'executor_ids' => [$employee->getKey()],
            ]), User::factory()->active()->create());
            $this->fail('Un non-associé ne doit pas devenir exécutant.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('executor_ids', $exception->errors());
        }

        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_ac_17_18_59_to_63_forecast_actual_variance_and_closure_regularization_are_explicit(): void
    {
        $client = Client::factory()->create();
        $contract = app(ContractService::class)->create($this->payload([
            'client_id' => $client->getKey(), 'forecast_profit_amount' => 500_000,
        ]), User::factory()->active()->create());
        Payment::factory()->create([
            'client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'received_amount' => 900_000,
        ]);
        Expense::factory()->create([
            'contract_id' => $contract->getKey(), 'requested_amount' => 300_000, 'state' => ExpenseState::Payee->value,
        ]);
        Expense::factory()->approved()->create([
            'contract_id' => $contract->getKey(), 'requested_amount' => 200_000,
        ]);

        $service = app(ContractService::class);
        $this->assertNull($service->summary($contract->fresh(['client', 'project', 'contributor.person', 'executors.user.person']))['regularization_proposal']);
        $service->close($contract, 'Prestations terminées et comptes vérifiés.', User::factory()->active()->create());
        $summary = $service->summary($contract->fresh(['client', 'project', 'contributor.person', 'executors.user.person']));

        $this->assertSame(900_000, $summary['received_amount']);
        $this->assertSame(300_000, $summary['direct_cost_amount']);
        $this->assertSame(600_000, $summary['actual_profit_amount']);
        $this->assertSame(100_000, $summary['variance_amount']);
        $this->assertSame(20, $summary['variance_percentage']);
        $this->assertSame('complement', $summary['regularization_proposal']['type']);
        $this->assertSame(100_000, $summary['regularization_proposal']['amount']);
        $this->assertDatabaseCount('expenses', 2);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Contract::class, 'auditable_id' => $contract->getKey(), 'action' => 'contract_closed']);
    }

    public function test_ac_20_and_50_executor_changes_preserve_history_and_closed_contracts_are_immutable(): void
    {
        $first = $this->associate();
        $second = $this->associate();
        $actor = User::factory()->active()->create();
        $service = app(ContractService::class);
        $contract = $service->create($this->payload([
            'contributor_id' => $actor->getKey(), 'has_execution' => true, 'executor_ids' => [$first->getKey()],
        ]), $actor);
        $service->update($contract, $this->payload([
            'reference' => $contract->reference, 'client_id' => $contract->client_id,
            'contributor_id' => $actor->getKey(), 'has_execution' => true, 'executor_ids' => [$second->getKey()],
        ]), $actor);

        $this->assertDatabaseHas('contract_executors', ['contract_id' => $contract->getKey(), 'user_id' => $first->getKey(), 'is_active' => false]);
        $this->assertDatabaseHas('contract_executors', ['contract_id' => $contract->getKey(), 'user_id' => $second->getKey(), 'is_active' => true]);
        $this->assertDatabaseCount('contract_executors', 2);

        $contract->forceFill(['state' => ContractState::Closed->value])->saveQuietly();
        $this->expectException(ValidationException::class);
        $service->update($contract, $this->payload(['reference' => $contract->reference, 'client_id' => $contract->client_id]), $actor);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'client_id' => Client::factory()->create()->getKey(), 'project_id' => null,
            'reference' => 'CTR-TEST-'.fake()->unique()->numerify('####'), 'title' => 'Contrat de test',
            'expected_total_amount' => 2_000_000, 'forecast_profit_amount' => 1_000_000,
            'contributor_id' => null, 'has_execution' => false, 'executor_ids' => [],
            'starts_on' => '2026-08-01', 'ends_on' => '2026-12-31',
        ], $overrides);
    }

    private function associate(): User
    {
        $user = User::factory()->active()->leader()->create();
        app(RoleAssignmentService::class)->assignRole($user, 'direction', null, 'Test story 8.1');

        return $user;
    }
}
