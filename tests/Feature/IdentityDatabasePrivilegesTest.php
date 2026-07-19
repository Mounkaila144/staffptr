<?php

namespace Tests\Feature;

use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Services\Identity\IdentityService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

class IdentityDatabasePrivilegesTest extends IdentityTestCase
{
    public function test_ac_5_application_account_can_update_but_cannot_delete_identity_tables(): void
    {
        $this->requireMysqlProof();

        $personWithoutAccount = Person::factory()->create();
        $person = Person::factory()->create();
        $user = User::factory()->for($person)->create();

        app(IdentityService::class)->updatePerson(
            $person,
            ['full_name' => 'Nom modifié'],
            null,
            'Direction test',
        );
        app(IdentityService::class)->updateUser(
            $user,
            ['failed_attempts' => 1],
            null,
            'Direction test',
        );

        $this->assertSame('Nom modifié', $person->fresh()->full_name);
        $this->assertSame(1, $user->fresh()->failed_attempts);

        foreach ([
            ['people', $personWithoutAccount->getKey()],
            ['users', $user->getKey()],
        ] as [$table, $identifier]) {
            try {
                DB::table($table)->where('id', $identifier)->delete();
                $this->fail("Le compte applicatif ne devait pas pouvoir supprimer dans {$table}.");
            } catch (QueryException $exception) {
                $this->assertSame(1142, $exception->errorInfo[1] ?? null);
            }
        }

        $this->assertDatabaseHas('people', ['id' => $personWithoutAccount->getKey()]);
        $this->assertDatabaseHas('users', ['id' => $user->getKey()]);
    }
}
