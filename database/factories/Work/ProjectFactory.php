<?php

namespace Database\Factories\Work;

use App\Enums\ProjectStatus;
use App\Models\Identity\User;
use App\Models\Work\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3), 'client_name' => fake()->optional()->company(),
            'manager_id' => User::factory(), 'start_date' => now('Africa/Niamey')->toDateString(),
            'end_date' => now('Africa/Niamey')->addMonth()->toDateString(), 'status' => ProjectStatus::Actif,
            'planned_budget_xof' => 500000, 'spent_budget_xof' => 100000,
        ];
    }
}
