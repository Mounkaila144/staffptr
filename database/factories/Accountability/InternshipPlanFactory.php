<?php

namespace Database\Factories\Accountability;

use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternshipPlan>
 */
class InternshipPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internship_id' => Internship::factory(),
            'skills_to_learn' => fake()->paragraph(),
            'objectives' => fake()->paragraph(),
            'weekly_tasks' => fake()->paragraph(),
            'expected_evidence' => fake()->paragraph(),
        ];
    }
}
