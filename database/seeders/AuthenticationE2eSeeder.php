<?php

namespace Database\Seeders;

use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Database\Seeder;
use LogicException;

class AuthenticationE2eSeeder extends Seeder
{
    public const PHONE = '+22790123456';

    public const PASSWORD = 'Temporaire-E2E-2026';

    public const DIRECTION_PHONE = '+22790234567';

    public const DIRECTION_PASSWORD = 'Direction-E2E-2026';

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

        $this->call(RolePermissionSeeder::class);

        $direction = User::query()->where('phone', self::DIRECTION_PHONE)->first();

        if (! $direction instanceof User) {
            $person = Person::factory()->create(['full_name' => 'Direction E2E Historique']);
            $direction = User::factory()->for($person)->create(['phone' => self::DIRECTION_PHONE]);
        }

        $direction->forceFill([
            'password' => self::DIRECTION_PASSWORD,
            'state' => UserState::Actif,
            'must_change_password' => false,
        ])->saveOrFail();
        app(RoleAssignmentService::class)->syncRoles(
            $direction,
            ['direction'],
            null,
            'Fixture E2E',
            'Accès à l’historique de connexion',
        );
    }
}
