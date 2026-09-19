<?php

namespace Database\Factories\Finance;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'category_id' => ExpenseCategory::factory(),
            'reason' => fake()->sentence(),
            'requested_amount' => fake()->numberBetween(1000, 500000),
            'beneficiary' => fake()->name(),
            'expected_result' => fake()->paragraph(),
            'project_or_contract_note' => fake()->optional(0.3)->sentence(),
            'state' => ExpenseState::Demandee->value,
            'cancel_reason' => null,
        ];
    }

    public function requested(): static
    {
        return $this->state(fn (): array => ['state' => ExpenseState::Demandee->value]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['state' => ExpenseState::Approuvee->value]);
    }

    public function refused(): static
    {
        return $this->state(fn (): array => ['state' => ExpenseState::Refusee->value]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'state' => ExpenseState::Annulee->value,
            'cancel_reason' => fake()->sentence(),
        ]);
    }

    public function withProjectNote(): static
    {
        return $this->state(fn (): array => [
            'project_or_contract_note' => fake()->sentence(),
        ]);
    }

    public function withoutProjectNote(): static
    {
        return $this->state(fn (): array => [
            'project_or_contract_note' => null,
        ]);
    }
}
