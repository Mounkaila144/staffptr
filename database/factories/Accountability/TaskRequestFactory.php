<?php

namespace Database\Factories\Accountability;

use App\Enums\TaskRequestState;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\TaskRequest;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskRequest>
 */
class TaskRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'daily_report_id' => DailyReport::factory(),
            'requested_by' => User::factory(),
            'responsible_id' => User::factory(),
            'description' => fake()->sentence(),
            'is_urgent' => false,
            'state' => TaskRequestState::Ouvert,
            'treated_by' => null,
            'treated_at' => null,
        ];
    }
}
