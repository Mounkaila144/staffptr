<?php

namespace Database\Factories\Work;

use App\Enums\ProjectStatus;
use App\Models\Identity\User;
use App\Models\Work\Project;
use App\Models\Work\ProjectStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectStatusHistory>
 */
class ProjectStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(), 'from_status' => ProjectStatus::Prevu,
            'to_status' => ProjectStatus::Actif, 'actor_id' => User::factory(),
            'reason' => 'Démarrage planifié du projet.', 'changed_at' => now('UTC'),
        ];
    }
}
