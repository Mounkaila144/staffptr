<?php

namespace Database\Factories\Finance;

use App\Enums\FinancialAccountState;
use App\Enums\FinancialAccountType;
use App\Models\Finance\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(FinancialAccountType::cases())->value,
            'label' => fake()->unique()->words(3, true),
            'opening_balance_amount' => fake()->numberBetween(0, 5_000_000),
            'opening_balance_date' => fake()->date(),
            'state' => FinancialAccountState::Active->value,
            'deactivation_reason' => null,
            'deactivated_at' => null,
        ];
    }
}
