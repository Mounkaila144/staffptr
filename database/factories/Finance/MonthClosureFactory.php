<?php

namespace Database\Factories\Finance;

use App\Enums\AlertLevel;
use App\Models\Finance\MonthClosure;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthClosure>
 */
class MonthClosureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => now('Africa/Niamey')->subMonth()->startOfMonth()->toDateString(),
            'version' => 1,
            'monthly_report_id' => null,
            'closed_by' => User::factory(),
            'closed_at' => now('UTC'),
            'reopened_by' => null,
            'reopened_at' => null,
            'reopen_reason' => null,
            'frozen_alert_level' => AlertLevel::Vert->value,
        ];
    }
}
