<?php

namespace Database\Factories\Accountability;

use App\Models\Accountability\TutorSupportSlot;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorSupportSlot>
 */
class TutorSupportSlotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tutor_id' => User::factory(),
            // Mardi 10 h, heure de Niamey.
            'weekday' => 2,
            'start_time' => '10:00:00',
        ];
    }

    public function on(int $weekday, string $startTime): self
    {
        return $this->state(fn (array $attributes): array => [
            'weekday' => $weekday,
            'start_time' => $startTime,
        ]);
    }
}
