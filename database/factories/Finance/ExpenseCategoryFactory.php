<?php

namespace Database\Factories\Finance;

use App\Models\Finance\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_essential' => false,
            'is_active' => true,
        ];
    }

    public function essential(): static
    {
        return $this->state(fn (): array => ['is_essential' => true]);
    }

    public function nonEssential(): static
    {
        return $this->state(fn (): array => ['is_essential' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
