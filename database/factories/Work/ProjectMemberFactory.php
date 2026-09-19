<?php

namespace Database\Factories\Work;

use App\Models\Identity\User;
use App\Models\Work\Project;
use App\Models\Work\ProjectMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMember>
 */
class ProjectMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(), 'user_id' => User::factory(),
            'joined_on' => now('Africa/Niamey')->toDateString(), 'added_by' => User::factory(),
        ];
    }
}
