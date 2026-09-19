<?php

namespace Tests\Feature;

use App\Enums\FinancialMovementDirection;
use App\Enums\ReconciliationState;
use App\Models\Finance\Account;
use App\Models\Finance\AccountMovement;
use App\Models\Identity\User;
use App\Services\Finance\ReconciliationService;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

class ReconciliationLifecycleTest extends IdentityTestCase
{
    public function test_ac_78_to_84_difference_is_always_calculated_controller_is_distinct_and_correction_is_versioned(): void
    {
        $account = Account::factory()->create(['opening_balance_amount' => 100_000, 'opening_balance_date' => '2026-08-01']);
        AccountMovement::factory()->create(['account_id' => $account, 'direction' => FinancialMovementDirection::Credit->value, 'movement_amount' => 50_000, 'effective_on' => '2026-08-05']);
        AccountMovement::factory()->create(['account_id' => $account, 'direction' => FinancialMovementDirection::Debit->value, 'movement_amount' => 20_000, 'effective_on' => '2026-08-06']);
        AccountMovement::factory()->create(['account_id' => $account, 'direction' => FinancialMovementDirection::Credit->value, 'movement_amount' => 999_000, 'effective_on' => '2026-08-20']);
        $preparer = User::factory()->active()->create();
        $controller = User::factory()->active()->create();
        $service = app(ReconciliationService::class);
        $draft = $service->prepare($this->payload($account, ['physical_balance_amount' => 125_000]), $preparer);

        $this->assertSame(130_000, $draft->calculated_balance_amount);
        $this->assertSame(5_000, $draft->difference_amount);
        $this->assertSame(FinancialMovementDirection::Debit, $draft->difference_direction);
        try {
            $service->validate($draft, $preparer);
            $this->fail('Le préparateur ne doit pas contrôler son rapprochement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('controlled_by', $exception->errors());
        }
        try {
            $service->validate($draft, $controller);
            $this->fail('Un écart non expliqué ne doit pas être validé.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('difference', $exception->errors());
        }

        $corrected = $service->prepare($this->payload($account, [
            'physical_balance_amount' => 130_000, 'difference_explanation' => null, 'responsible_id' => null,
            'corrective_action' => null, 'correction_reason' => 'Nouveau comptage physique contradictoire.',
        ]), $preparer, $draft);
        $validated = $service->validate($corrected, $controller);
        $this->assertSame(ReconciliationState::Validated, $validated->state);
        $this->assertSame(0, $validated->difference_amount);
        $this->assertSame($draft->getKey(), $validated->previous_id);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $validated::class, 'auditable_id' => $validated->getKey(), 'action' => 'reconciliation_validated']);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(Account $account, array $overrides = []): array
    {
        return array_replace([
            'account_id' => $account->getKey(), 'period_start' => '2026-08-03', 'period_end' => '2026-08-09',
            'physical_balance_amount' => 130_000, 'difference_explanation' => null, 'responsible_id' => null,
            'corrective_action' => null,
        ], $overrides);
    }
}
