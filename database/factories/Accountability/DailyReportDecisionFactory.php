<?php

namespace Database\Factories\Accountability;

use App\Enums\DailyReportDecisionType;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportDecision;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyReportDecision>
 */
class DailyReportDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'daily_report_id' => DailyReport::factory(),
            'reviewer_id' => User::factory(),
            'decision' => DailyReportDecisionType::Valider,
            'reason' => null,
            'decided_at' => now('UTC'),
        ];
    }
}
