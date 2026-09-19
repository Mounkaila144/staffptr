<?php

namespace Database\Factories\Work;

use App\Enums\ObjectiveState;
use App\Enums\WorkPriority;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Objective>
 */
class ObjectiveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'created_by' => User::factory(), 'title' => fake()->sentence(4),
            'description' => fake()->sentence(), 'indicator' => 'Taux de réalisation', 'target_value' => '100 %',
            'expected_evidence' => 'Document de validation signé', 'required_means' => 'Temps et accompagnement',
            'due_date' => now('Africa/Niamey')->endOfMonth()->toDateString(), 'priority' => WorkPriority::Normale,
            'state' => ObjectiveState::Brouillon, 'progress' => 0, 'version_number' => 1,
        ];
    }
}
