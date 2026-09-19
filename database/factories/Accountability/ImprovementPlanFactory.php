<?php

namespace Database\Factories\Accountability;

use App\Enums\ImprovementPlanState;
use App\Models\Accountability\ImprovementPlan;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImprovementPlan>
 */
class ImprovementPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = CarbonImmutable::now('Africa/Niamey')->startOfDay();

        return [
            'weekly_review_id' => WeeklyReview::factory(),
            'subject_user_id' => User::factory(),
            'created_by' => User::factory(),
            'start_date' => $startDate->toDateString(),
            // Durée par défaut de 10 jours, dans les bornes de 7 à 14 (AC 9).
            'end_date' => $startDate->addDays(9)->toDateString(),
            'support_provided' => fake()->paragraph(),
            'observed_result' => null,
            'state' => ImprovementPlanState::EnCours,
            'closed_by' => null,
            'closed_at' => null,
        ];
    }

    public function closed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'state' => ImprovementPlanState::Termine,
            'observed_result' => fake()->paragraph(),
            'closed_at' => CarbonImmutable::now('UTC'),
        ])->afterMaking(function (ImprovementPlan $plan): void {
            $plan->closed_by = $plan->created_by;
        });
    }

    /**
     * Fixe la durée du plan, bornes incluses, à partir de la date de début effective.
     */
    public function lastingDays(int $days): self
    {
        return $this->afterMaking(function (ImprovementPlan $plan) use ($days): void {
            $plan->end_date = CarbonImmutable::parse($plan->start_date)->addDays($days - 1);
        });
    }
}
