<?php

namespace Database\Factories\Accountability;

use App\Models\Accountability\AttendanceRecord;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $day = now('Africa/Niamey')->startOfDay();

        return [
            'user_id' => User::factory(),
            'work_date' => $day->toDateString(),
            // 8 h 00 à Niamey, soit 7 h 00 UTC : à l'heure, sans retard.
            'arrived_at' => $day->setTime(8, 0)->utc(),
            'arrival_recorded_by' => User::factory(),
            'left_at' => null,
            'departure_recorded_by' => null,
            'expected_arrival_time' => '08:00',
            'tolerance_minutes' => 10,
            'late_minutes' => 0,
        ];
    }
}
