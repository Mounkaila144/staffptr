<?php

namespace Database\Factories\Finance;

use App\Enums\ReconciliationState;
use App\Models\Finance\Account;
use App\Models\Finance\Reconciliation;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reconciliation>
 */
class ReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $balance = fake()->numberBetween(0, 10_000_000);

        return [
            'account_id' => Account::factory(),
            'period_start' => now('Africa/Niamey')->startOfWeek()->toDateString(),
            'period_end' => now('Africa/Niamey')->endOfWeek()->toDateString(),
            'calculated_balance_amount' => $balance,
            'physical_balance_amount' => $balance,
            'difference_direction' => null,
            'difference_amount' => 0,
            'difference_explanation' => null,
            'responsible_id' => null,
            'corrective_action' => null,
            'prepared_by' => User::factory(),
            'controlled_by' => null,
            'state' => ReconciliationState::Draft->value,
            'validated_at' => null,
            'previous_id' => null,
            'correction_reason' => null,
        ];
    }
}
