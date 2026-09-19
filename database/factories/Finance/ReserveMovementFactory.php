<?php

namespace Database\Factories\Finance;

use App\Enums\ReserveMovementType;
use App\Models\Finance\ReserveMovement;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReserveMovement>
 */
class ReserveMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ReserveMovementType::Allocation->value,
            'movement_amount' => fake()->numberBetween(1_000, 1_000_000),
            'payment_id' => null,
            'expense_id' => null,
            'reversal_of_id' => null,
            'occurred_on' => now('Africa/Niamey')->toDateString(),
            'reason' => null,
            'reconstitution_plan' => null,
            'first_approved_by' => null,
            'second_approved_by' => null,
            'created_by' => User::factory(),
            'idempotency_key' => (string) Str::ulid(),
        ];
    }
}
