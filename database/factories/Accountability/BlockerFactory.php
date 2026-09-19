<?php

namespace Database\Factories\Accountability;

use App\Enums\BlockerState;
use App\Enums\BlockerUrgency;
use App\Models\Accountability\Blocker;
use App\Models\Identity\User;
use App\Models\Work\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blocker>
 */
class BlockerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'origin_type' => Task::class,
            'origin_id' => Task::factory(),
            'created_by' => User::factory(),
            'solicited_user_id' => User::factory(),
            'problem' => fake()->paragraph(),
            'urgency' => BlockerUrgency::Normale,
            'reported_on' => now('Africa/Niamey')->toDateString(),
            'deadline_impact' => fake()->sentence(),
            'attempted_action' => fake()->sentence(),
            'state' => BlockerState::Ouvert,
            'acknowledged_at' => null,
            'resolved_at' => null,
            'closure_reason' => null,
        ];
    }
}
