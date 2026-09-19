<?php

namespace Database\Factories\Finance;

use App\Models\Finance\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => '+227'.fake()->unique()->numerify('########'),
            'contact' => fake()->optional()->name(),
            'notes' => fake()->optional()->paragraph(),
            'is_active' => true,
        ];
    }
}
