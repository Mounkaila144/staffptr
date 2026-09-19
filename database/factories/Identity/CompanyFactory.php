<?php

namespace Database\Factories\Identity;

use App\Models\Identity\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
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
            'phone' => '+227'.fake()->numerify('########'),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'logo_path' => null,
        ];
    }
}
