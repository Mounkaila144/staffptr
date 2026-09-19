<?php

namespace Database\Factories\Identity;

use App\Models\Identity\JobFunction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobFunction>
 */
class JobFunctionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
