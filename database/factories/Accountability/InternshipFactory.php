<?php

namespace Database\Factories\Accountability;

use App\Enums\InternshipState;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Internship>
 */
class InternshipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'internship_intake_form_id' => InternshipIntakeForm::factory()->approved(),
            'tutor_id' => User::factory(),
            'state' => InternshipState::Actif,
            'start_date' => CarbonImmutable::now('Africa/Niamey')->toDateString(),
            'end_date' => null,
            'ended_at' => null,
        ];
    }

    /**
     * Stage terminé : il libère la place occupée chez le tuteur (AC 23).
     */
    public function ended(): self
    {
        return $this->state(fn (array $attributes): array => [
            'state' => InternshipState::Termine,
            'end_date' => CarbonImmutable::now('Africa/Niamey')->toDateString(),
            'ended_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function archived(): self
    {
        return $this->ended()->state(fn (array $attributes): array => [
            'state' => InternshipState::Archive,
        ]);
    }

    public function forTutor(User $tutor): self
    {
        return $this->state(fn (array $attributes): array => [
            'tutor_id' => $tutor->getKey(),
        ]);
    }
}
