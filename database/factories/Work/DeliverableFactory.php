<?php

namespace Database\Factories\Work;

use App\Enums\DeliverableStatus;
use App\Models\Identity\User;
use App\Models\Work\Deliverable;
use App\Models\Work\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deliverable>
 */
class DeliverableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(), 'owner_id' => User::factory(), 'title' => fake()->sentence(4),
            'planned_date' => now('Africa/Niamey')->addWeek()->toDateString(), 'status' => DeliverableStatus::Prevu,
        ];
    }
}
