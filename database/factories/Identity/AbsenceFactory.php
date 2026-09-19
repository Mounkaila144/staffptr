<?php

namespace Database\Factories\Identity;

use App\Enums\AbsenceState;
use App\Enums\AbsenceType;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Absence> */
class AbsenceFactory extends Factory
{
    public function definition(): array
    {
        $start = CarbonImmutable::instance(fake()->dateTimeBetween('now', '+3 months'));

        return [
            'user_id' => User::factory()->active(),
            'type' => fake()->randomElement(AbsenceType::cases()),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->addDays(fake()->numberBetween(0, 5))->format('Y-m-d'),
            'reason' => fake()->sentence(6),
            'state' => AbsenceState::Demandee,
            'decision_reason' => null,
            'decided_by' => null,
            'decided_at' => null,
        ];
    }

    public function requested(): static
    {
        return $this->state(fn (): array => [
            'state' => AbsenceState::Demandee,
            'decision_reason' => null,
            'decided_by' => null,
            'decided_at' => null,
        ]);
    }

    public function approved(?User $manager = null): static
    {
        return $this->state(fn (): array => [
            'state' => AbsenceState::Approuvee,
            'decision_reason' => null,
            'decided_by' => $manager?->getKey() ?? User::factory()->active(),
            'decided_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function refused(?User $manager = null): static
    {
        return $this->state(fn (): array => [
            'state' => AbsenceState::Refusee,
            'decision_reason' => 'La présence est nécessaire sur cette période.',
            'decided_by' => $manager?->getKey() ?? User::factory()->active(),
            'decided_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'state' => AbsenceState::Annulee,
            'decision_reason' => null,
            'decided_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
