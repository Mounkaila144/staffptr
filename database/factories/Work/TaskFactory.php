<?php

namespace Database\Factories\Work;

use App\Enums\WorkPriority;
use App\Enums\WorkTaskStatus;
use App\Models\Identity\User;
use App\Models\Work\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignee_id' => User::factory(), 'created_by' => User::factory(), 'title' => fake()->sentence(5),
            'due_date' => now('Africa/Niamey')->toDateString(), 'priority' => WorkPriority::Normale,
            'status' => WorkTaskStatus::AFaire,
        ];
    }
}
