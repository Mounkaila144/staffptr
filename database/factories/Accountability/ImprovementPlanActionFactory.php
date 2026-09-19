<?php

namespace Database\Factories\Accountability;

use App\Models\Accountability\ImprovementPlan;
use App\Models\Accountability\ImprovementPlanAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImprovementPlanAction>
 */
class ImprovementPlanActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'improvement_plan_id' => ImprovementPlan::factory(),
            'position' => 1,
            'description' => fake()->sentence(),
            'due_date' => CarbonImmutable::now('Africa/Niamey')->addDays(5)->toDateString(),
            'completed_at' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'completed_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
