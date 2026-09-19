<?php

namespace Database\Factories\Accountability;

use App\Enums\InternshipIntakeState;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Accountability\InternshipIntakeOutcome;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternshipIntakeForm>
 */
class InternshipIntakeFormFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_user_id' => User::factory(),
            'manager_id' => User::factory(),
            'tutor_id' => User::factory(),
            'real_need' => fake()->paragraph(),
            'mission' => fake()->paragraph(),
            'duration_weeks' => 12,
            'tools' => fake()->sentence(),
            'state' => InternshipIntakeState::Brouillon,
            'submitted_at' => null,
            'decided_by' => null,
            'decided_at' => null,
            'decision_reason' => null,
        ];
    }

    /**
     * Fiche complète de ses trois résultats obligatoires (AC 14).
     */
    public function withRequiredOutcomes(): self
    {
        return $this->afterCreating(function (InternshipIntakeForm $form): void {
            foreach (range(1, InternshipIntakeForm::REQUIRED_OUTCOMES) as $position) {
                InternshipIntakeOutcome::factory()->create([
                    'internship_intake_form_id' => $form->getKey(),
                    'position' => $position,
                ]);
            }
        });
    }

    public function submitted(): self
    {
        return $this->withRequiredOutcomes()->state(fn (array $attributes): array => [
            'state' => InternshipIntakeState::Soumise,
            'submitted_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function approved(): self
    {
        return $this->withRequiredOutcomes()->state(fn (array $attributes): array => [
            'state' => InternshipIntakeState::Approuvee,
            'submitted_at' => CarbonImmutable::now('UTC'),
            'decided_by' => User::factory(),
            'decided_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
