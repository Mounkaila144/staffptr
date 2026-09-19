<?php

namespace Database\Factories\Accountability;

use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyReportVersion>
 */
class DailyReportVersionFactory extends Factory
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
            'version_number' => 1,
            'author_id' => User::factory(),
            'idempotency_key' => fake()->uuid(),
            'planned_task' => fake()->sentence(),
            'achieved_result' => fake()->paragraph(),
            'evidence_link' => fake()->url(),
            'blocker_present' => false,
            'blocker_details' => null,
            'next_action' => fake()->sentence(),
            'help_requested' => false,
            'help_details' => null,
            'correction_reason' => null,
        ];
    }
}
