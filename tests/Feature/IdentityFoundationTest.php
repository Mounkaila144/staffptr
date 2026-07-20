<?php

namespace Tests\Feature;

use App\Enums\PersonOperationalStatus;
use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Services\Identity\IdentityService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Support\IdentityTestCase;

class IdentityFoundationTest extends IdentityTestCase
{
    public function test_ac_1_people_and_users_are_distinct_identity_and_access_tables(): void
    {
        $this->assertTrue(Schema::hasColumns('people', [
            'id', 'full_name', 'operational_status', 'first_seen_at',
        ]));
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'person_id', 'phone', 'phone_unique_key', 'password', 'state',
            'must_change_password', 'locked_until', 'failed_attempts',
        ]));
        $this->assertFalse(Schema::hasColumn('people', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('users', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('users', 'email'));
    }

    public function test_ac_2_one_account_belongs_to_one_person_and_person_has_successive_accounts(): void
    {
        $person = Person::factory()->create();
        $first = User::factory()->for($person)->active()->create();
        $second = User::factory()->for($person)->archived()->create();

        $this->assertTrue($first->person->is($person));
        $this->assertTrue($second->person->is($person));
        $this->assertCount(2, $person->fresh()->users);
    }

    public function test_ac_3_archiving_an_account_leaves_the_person_intact_and_consultable(): void
    {
        $person = Person::factory()->create();
        $user = User::factory()->for($person)->active()->create();

        app(IdentityService::class)->changeUserState(
            $user,
            UserState::Archive,
            null,
            'Direction test',
            'Départ de la personne',
        );

        $this->assertDatabaseHas('people', ['id' => $person->getKey()]);
        $this->assertSame(UserState::Archive, $user->fresh()->state);
        $this->assertTrue($user->fresh()->person->is($person));
    }

    public function test_ac_4_return_creates_a_new_account_on_the_existing_person_with_both_histories(): void
    {
        $person = Person::factory()->create();
        $phone = '+22790112233';
        $first = User::factory()->for($person)->archived()->create(['phone' => $phone]);
        $second = app(IdentityService::class)->createUser(
            $person,
            [
                'phone' => $phone,
                'password' => 'Nouveau-Mot-De-Passe-2026',
                'state' => UserState::Actif,
                'must_change_password' => false,
            ],
            null,
            'Direction test',
            'Retour dans la structure',
        );

        $accounts = $person->fresh()->users()->orderBy('id')->get();

        $this->assertCount(2, $accounts);
        $this->assertTrue($accounts->contains($first));
        $this->assertTrue($accounts->contains($second));
        $this->assertSame(UserState::Archive, $accounts->first()->state);
        $this->assertSame(UserState::Actif, $accounts->last()->state);
    }

    public function test_ac_5_models_refuse_physical_deletion_without_soft_deletes(): void
    {
        $person = Person::factory()->create();
        $user = User::factory()->for($person)->create();

        foreach ([$person, $user] as $model) {
            try {
                $model->delete();
                $this->fail('La suppression physique devait être refusée.');
            } catch (LogicException $exception) {
                $this->assertSame(
                    'La suppression physique de cette ressource est interdite.',
                    $exception->getMessage(),
                );
            }
        }

        $this->assertDatabaseHas('people', ['id' => $person->getKey()]);
        $this->assertDatabaseHas('users', ['id' => $user->getKey()]);
    }

    public function test_ac_2_user_is_a_phone_based_laravel_authentication_contract(): void
    {
        $user = User::factory()->create([
            'phone' => '90 11 22 44',
            'password' => 'Mot-De-Passe-Secret-2026',
        ]);

        $this->assertInstanceOf(Authenticatable::class, $user);
        $this->assertSame(User::class, config('auth.providers.users.model'));
        $this->assertSame('+22790112244', $user->phone);
        $this->assertTrue(Hash::check('Mot-De-Passe-Secret-2026', $user->password));

        $hashingConfiguration = file_get_contents(config_path('hashing.php'));
        $this->assertIsString($hashingConfiguration);
        $this->assertStringContainsString("env('BCRYPT_ROUNDS', 12)", $hashingConfiguration);
    }

    public function test_ac_3_person_exit_is_a_civil_date_and_state_not_a_deletion(): void
    {
        $person = Person::factory()->create(['first_seen_at' => '2024-01-31']);

        app(IdentityService::class)->changePersonStatus(
            $person,
            PersonOperationalStatus::Sorti,
            null,
            'Direction test',
            'Fin de collaboration',
        );

        $this->assertSame('2024-01-31', $person->fresh()->first_seen_at->format('Y-m-d'));
        $this->assertSame(PersonOperationalStatus::Sorti, $person->fresh()->operational_status);
        $this->assertDatabaseHas('people', ['id' => $person->getKey()]);
    }
}
