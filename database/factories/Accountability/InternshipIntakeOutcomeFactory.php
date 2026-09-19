<?php

namespace Database\Factories\Accountability;

use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Accountability\InternshipIntakeOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternshipIntakeOutcome>
 */
class InternshipIntakeOutcomeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internship_intake_form_id' => InternshipIntakeForm::factory(),
            'position' => 1,
            'description' => fake()->sentence(),
        ];
    }
}
