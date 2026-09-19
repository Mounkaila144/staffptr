<?php

namespace Database\Factories\Finance;

use App\Models\Finance\FixedCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedCharge>
 */
class FixedChargeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->words(2, true),
            'monthly_amount' => fake()->numberBetween(10_000, 2_000_000),
            'is_active' => true,
        ];
    }
}
