<?php

namespace Database\Factories\Identity;

use App\Models\Identity\LoginAttempt;
use App\Models\Identity\User;
use App\Services\Identity\AttemptedPhoneFingerprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginAttempt>
 */
class LoginAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '+227'.fake()->numerify('########');

        return [
            'user_id' => User::factory(),
            'phone_attempted' => app(AttemptedPhoneFingerprint::class)->for($phone),
            'successful' => fake()->boolean(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'occurred_at' => now(),
            'lock_expires_at' => null,
        ];
    }
}
