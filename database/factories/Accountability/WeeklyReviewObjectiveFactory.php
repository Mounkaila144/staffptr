<?php

namespace Database\Factories\Accountability;

use App\Enums\ReviewObjectiveStatus;
use App\Models\Accountability\WeeklyReview;
use App\Models\Accountability\WeeklyReviewObjective;
use App\Models\Work\Objective;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyReviewObjective>
 */
class WeeklyReviewObjectiveFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'weekly_review_id' => WeeklyReview::factory(),
            'objective_id' => Objective::factory(),
            'result' => fake()->sentence(),
            'evidence' => fake()->sentence(),
            'status' => ReviewObjectiveStatus::Atteint,
            'gap_cause' => null,
            'next_action' => fake()->sentence(),
        ];
    }

    public function withGap(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewObjectiveStatus::PartiellementAtteint,
            'gap_cause' => fake()->sentence(),
        ]);
    }
}
