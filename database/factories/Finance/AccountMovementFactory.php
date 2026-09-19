<?php

namespace Database\Factories\Finance;

use App\Enums\FinancialMovementDirection;
use App\Models\Finance\Account;
use App\Models\Finance\AccountMovement;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountMovement>
 */
class AccountMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'direction' => FinancialMovementDirection::Credit->value,
            'movement_amount' => fake()->numberBetween(1_000, 1_000_000),
            'effective_on' => fake()->date(),
            'source_type' => 'factory',
            'source_id' => fake()->unique()->numberBetween(1, 2_000_000_000),
            'description' => fake()->sentence(),
            'reversal_of_id' => null,
            'created_by' => User::factory(),
            'validated_at' => now('UTC'),
        ];
    }
}
