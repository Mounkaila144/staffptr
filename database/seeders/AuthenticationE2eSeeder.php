<?php

namespace Database\Seeders;

use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use Illuminate\Database\Seeder;
use LogicException;

class AuthenticationE2eSeeder extends Seeder
{
    public const PHONE = '+22790123456';

    public const PASSWORD = 'Temporaire-E2E-2026';

    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException("La fixture d'authentification E2E est réservée aux tests.");
        }

        $user = User::query()->where('phone', self::PHONE)->first();

        if (! $user instanceof User) {
            $person = Person::factory()->create(['full_name' => 'Compte E2E Authentification']);
            $user = User::factory()->for($person)->create(['phone' => self::PHONE]);
        }

        $user->forceFill([
            'password' => self::PASSWORD,
            'state' => UserState::Actif,
            'must_change_password' => true,
        ])->saveOrFail();
    }
}
