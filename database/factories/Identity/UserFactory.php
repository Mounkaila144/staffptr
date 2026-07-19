<?php

namespace Database\Factories\Identity;

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
}
