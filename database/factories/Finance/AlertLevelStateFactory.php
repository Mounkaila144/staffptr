<?php

namespace Database\Factories\Finance;

use App\Enums\AlertLevel;
use App\Models\Finance\AlertLevelState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertLevelState>
 */
class AlertLevelStateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => now('Africa/Niamey')->startOfMonth()->toDateString(),
            'level' => AlertLevel::Vert->value,
            'baseline_amount' => 0,
            'collections_amount' => 0,
            'previous_collections_amount' => 0,
            'source_date' => now('Africa/Niamey')->toDateString(),
            'observed_at' => now('UTC'),
            'calculated_at' => now('UTC'),
        ];
    }

    public function orange(): self
    {
        return $this->state(fn (array $attributes): array => [
            'level' => AlertLevel::Orange->value,
            'baseline_amount' => 1_000_000,
            'collections_amount' => 400_000,
            'previous_collections_amount' => 1_500_000,
        ]);
    }

    public function rouge(): self
    {
        return $this->state(fn (array $attributes): array => [
            'level' => AlertLevel::Rouge->value,
            'baseline_amount' => 1_000_000,
            'collections_amount' => 400_000,
            'previous_collections_amount' => 300_000,
        ]);
    }
}
