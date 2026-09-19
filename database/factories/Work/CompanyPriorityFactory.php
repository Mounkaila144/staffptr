<?php

namespace Database\Factories\Work;

use App\Enums\CompanyPriorityState;
use App\Enums\WorkPriority;
use App\Models\Identity\User;
use App\Models\Work\CompanyPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyPriority>
 */
class CompanyPriorityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => now('Africa/Niamey')->startOfMonth()->toDateString(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'owner_id' => User::factory(),
            'indicator' => 'Résultat mesurable',
            'target' => '100 %',
            'due_date' => now('Africa/Niamey')->endOfMonth()->toDateString(),
            'priority' => WorkPriority::Haute,
            'state' => CompanyPriorityState::Validee,
        ];
    }
}
