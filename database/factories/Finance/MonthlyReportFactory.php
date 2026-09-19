<?php

namespace Database\Factories\Finance;

use App\Enums\MonthlyReportState;
use App\Models\Finance\MonthlyReport;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyReport>
 */
class MonthlyReportFactory extends Factory
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
            'state' => MonthlyReportState::Draft->value,
            'lines' => [],
            'prepared_by' => User::factory(),
            'controlled_by' => null,
            'validated_by' => null,
            'controlled_at' => null,
            'validated_at' => null,
            'alert_level' => null,
            'alert_source_date' => null,
        ];
    }
}
