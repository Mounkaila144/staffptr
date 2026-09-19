<?php

namespace Database\Factories\Finance;

use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\MonthlyBudget;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyBudget>
 */
class MonthlyBudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => ExpenseCategory::factory(),
            'month' => now('Africa/Niamey')->startOfMonth()->toDateString(),
            'budget_amount' => fake()->numberBetween(10_000, 5_000_000),
            'created_by' => User::factory(),
        ];
    }
}
