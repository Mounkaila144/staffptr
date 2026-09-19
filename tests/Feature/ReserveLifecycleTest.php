<?php

namespace Tests\Feature;

use App\Models\Finance\Contract;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\Payment;
use App\Models\Finance\ReserveMovement;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;
use App\Services\Finance\ReserveService;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

class ReserveLifecycleTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ac_70_75_and_77_summary_uses_active_fixed_charges_and_explains_method_and_source(): void
    {
        FixedCharge::factory()->create(['monthly_amount' => 100_000, 'is_active' => true]);
        FixedCharge::factory()->create(['monthly_amount' => 900_000, 'is_active' => false]);
        ReserveMovement::factory()->create(['movement_amount' => 150_000, 'approval_state' => 'approved']);

        $summary = app(ReserveService::class)->summary();
        $this->assertSame(300_000, $summary['objective_amount']);
        $this->assertSame(150_000, $summary['amount']);
        $this->assertSame(1.5, $summary['covered_months']);
        $this->assertStringContainsString('20 %', $summary['method']);
        $this->assertNotEmpty($summary['source_date']);
    }

    public function test_ac_76_reserve_usage_requires_reason_plan_and_two_distinct_direction_approvals(): void
    {
        ReserveMovement::factory()->create(['movement_amount' => 500_000, 'approval_state' => 'approved']);
        $finance = $this->roleUser('finance');
        $first = $this->roleUser('direction');
        $second = $this->roleUser('direction');
        $service = app(ReserveService::class);

        try {
            $service->requestUsage(100_000, '', '', (string) Str::ulid(), $finance);
            $this->fail('Motif et plan doivent être obligatoires.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reserve', $exception->errors());
        }

        $usage = $service->requestUsage(100_000, 'Urgence de trésorerie.', 'Reconstitution sur les deux prochains encaissements.', (string) Str::ulid(), $finance);
        $this->assertSame(500_000, $service->currentAmount());
        $service->approveUsage($usage, $first);
        $this->assertSame(500_000, $service->currentAmount());
        try {
            $service->approveUsage($usage, $first);
            $this->fail('Un même approbateur ne signe pas deux fois.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval', $exception->errors());
        }
        $service->approveUsage($usage, $second);
        $this->assertSame(400_000, $service->currentAmount());
        $this->assertSame('approved', $usage->refresh()->approval_state);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => ReserveMovement::class, 'auditable_id' => $usage->getKey(), 'action' => 'reserve_usage_approved']);
    }

    public function test_ac_74_allocation_stops_at_objective_and_restarts_after_an_approved_usage(): void
    {
        FixedCharge::factory()->create(['monthly_amount' => 100_000, 'is_active' => true]);
        ReserveMovement::factory()->create(['movement_amount' => 290_000, 'approval_state' => 'approved']);
        $contract = Contract::factory()->create(['contributor_id' => User::factory()->active(), 'has_execution' => true]);
        $actor = User::factory()->active()->create();
        $service = app(ReserveService::class);
        $allocate = function () use ($contract, $actor, $service): ?ReserveMovement {
            $payment = Payment::factory()->create(['contract_id' => $contract->getKey(), 'client_id' => $contract->client_id]);
            ShareEntitlement::factory()->create(['contract_id' => $contract->getKey(), 'payment_id' => $payment->getKey(), 'base_amount' => 100_000]);

            return DB::transaction(fn (): ?ReserveMovement => $service->allocateFrom($payment, $contract, $actor));
        };

        $this->assertSame(10_000, $allocate()?->movement_amount);
        $this->assertNull($allocate());
        $usage = $service->requestUsage(50_000, 'Besoin temporaire.', 'Prochain contrat.', (string) Str::ulid(), $this->roleUser('finance'));
        $service->approveUsage($usage, $this->roleUser('direction'));
        $service->approveUsage($usage, $this->roleUser('direction'));
        $this->assertSame(20_000, $allocate()?->movement_amount);
        $this->assertSame(270_000, $service->currentAmount());
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test réserve story 8.1');

        return $user;
    }
}
