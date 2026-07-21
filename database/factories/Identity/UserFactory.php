<?php

namespace Database\Factories\Identity;

use App\Enums\RelationType;
use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'phone' => '+227'.fake()->unique()->numerify('########'),
            'password' => 'MotDePasse-Test-2026',
            'state' => UserState::Invite,
            'must_change_password' => true,
            'locked_until' => null,
            'failed_attempts' => 0,
            'relation_type' => RelationType::Employe,
            'contract_start_date' => null,
            'contract_end_date' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'state' => UserState::Actif,
            'must_change_password' => false,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['state' => UserState::Suspendu]);
    }

    public function terminated(): static
    {
        return $this->state(fn (): array => ['state' => UserState::Termine]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['state' => UserState::Archive]);
    }

    public function leader(): static
    {
        return $this->state(fn (): array => ['relation_type' => RelationType::Dirigeant]);
    }

    public function employee(): static
    {
        return $this->state(fn (): array => ['relation_type' => RelationType::Employe]);
    }

    public function contractor(): static
    {
        return $this->state(fn (): array => ['relation_type' => RelationType::Contractuel]);
    }

    public function intern(): static
    {
        return $this->state(fn (): array => ['relation_type' => RelationType::Stagiaire]);
    }

    public function withManager(?User $manager = null): static
    {
        return $this->state(fn (): array => ['manager_id' => $manager?->getKey() ?? User::factory()->active()]);
    }

    public function withoutManager(): static
    {
        return $this->state(fn (): array => ['manager_id' => null]);
    }

    public function endingInDays(int $days): static
    {
        return $this->state(fn (): array => [
            'relation_type' => RelationType::Contractuel,
            'contract_start_date' => today('Africa/Niamey')->subMonth()->toDateString(),
            'contract_end_date' => today('Africa/Niamey')->addDays($days)->toDateString(),
        ]);
    }
}
