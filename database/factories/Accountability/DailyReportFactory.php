<?php

namespace Database\Factories\Accountability;

use App\Enums\DailyReportState;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyReport>
 */
class DailyReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'report_date' => now('Africa/Niamey')->toDateString(),
            'state' => DailyReportState::Brouillon,
            'submitted_at' => null,
            'lateness_explanation' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'state' => DailyReportState::Envoye,
            'submitted_at' => now('UTC'),
        ]);
    }
}
