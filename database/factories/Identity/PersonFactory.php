<?php

namespace Database\Factories\Identity;

use App\Enums\PersonOperationalStatus;
use App\Models\Identity\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'operational_status' => PersonOperationalStatus::Actif,
            'first_seen_at' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
        ];
    }

    public function exited(): static
    {
        return $this->state(fn (): array => [
            'operational_status' => PersonOperationalStatus::Sorti,
        ]);
    }
}
