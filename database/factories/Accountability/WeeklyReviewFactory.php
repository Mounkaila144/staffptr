<?php

namespace Database\Factories\Accountability;

use App\Enums\WeeklyReviewState;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyReview>
 */
class WeeklyReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $weekStart = CarbonImmutable::now('Africa/Niamey')->startOfWeek();

        return [
            'subject_user_id' => User::factory(),
            'reviewer_id' => User::factory(),
            'week_start_date' => $weekStart->toDateString(),
            'scheduled_on' => $weekStart->addDays(4)->toDateString(),
            'state' => WeeklyReviewState::Brouillon,
            'reviewee_comment' => null,
            'reviewer_comment' => null,
            'reviewee_validated_by' => null,
            'reviewee_validated_at' => null,
            'reviewer_validated_by' => null,
            'reviewer_validated_at' => null,
        ];
    }

    /**
     * Revue soumise aux deux validations, aucune encore posée.
     */
    public function awaitingValidation(): self
    {
        return $this->state(fn (array $attributes): array => [
            'state' => WeeklyReviewState::EnAttenteValidation,
            'reviewer_comment' => fake()->paragraph(),
        ]);
    }

    /**
     * Revue validée par les deux parties : elle est dès lors figée (AC 7).
     */
    public function validated(): self
    {
        return $this->state(fn (array $attributes): array => [
            'state' => WeeklyReviewState::Validee,
            'reviewee_comment' => fake()->paragraph(),
            'reviewer_comment' => fake()->paragraph(),
            'reviewee_validated_at' => CarbonImmutable::now('UTC'),
            'reviewer_validated_at' => CarbonImmutable::now('UTC'),
            // Les validateurs sont résolus après application des surcharges, pour rester
            // nominativement les deux parties effectives de la revue (AC 4).
        ])->afterMaking(function (WeeklyReview $review): void {
            $review->reviewee_validated_by = $review->subject_user_id;
            $review->reviewer_validated_by = $review->reviewer_id;
        });
    }
}
