<?php

namespace Database\Factories\Finance;

use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseApproval>
 */
class ExpenseApprovalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'approver_id' => User::factory(),
            'decision' => fake()->randomElement(['approve', 'reject']),
            'comment' => fake()->optional(0.5)->sentence(),
            'decided_at' => fake()->optional(0.8)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'decision' => 'approve',
            'decided_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'decision' => 'reject',
            'decided_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'decided_at' => null,
        ]);
    }

    public function withComment(): static
    {
        return $this->state(fn (): array => [
            'comment' => fake()->sentence(),
        ]);
    }
}
