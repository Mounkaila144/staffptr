<?php

namespace Database\Factories\Accountability;

use App\Enums\InternshipEvaluationType;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipEvaluation;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternshipEvaluation>
 */
class InternshipEvaluationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internship_id' => Internship::factory(),
            'evaluator_id' => User::factory(),
            'type' => InternshipEvaluationType::Hebdomadaire,
            'week_start_date' => CarbonImmutable::now('Africa/Niamey')->startOfWeek()->toDateString(),
            'observed_progress' => fake()->paragraph(),
            'evidence' => fake()->sentence(),
            'next_steps' => fake()->sentence(),
            'certificate_conditions_met' => null,
            'validated_at' => null,
        ];
    }

    /**
     * Évaluation finale : elle porte l'indication d'éligibilité à l'attestation (AC 29).
     */
    public function finale(bool $conditionsMet = true): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => InternshipEvaluationType::Finale,
            'week_start_date' => null,
            'certificate_conditions_met' => $conditionsMet,
        ]);
    }

    /**
     * Évaluation validée : elle n'est plus ni modifiable ni supprimable (AC 32).
     */
    public function validated(): self
    {
        return $this->state(fn (array $attributes): array => [
            'validated_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
