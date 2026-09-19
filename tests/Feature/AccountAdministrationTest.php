<?php

namespace Tests\Feature;

use App\Enums\UserState;
use App\Exceptions\Identity\RoleAssignmentConflict;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Identity\AccountAdministrationService;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Hash;
use Tests\Support\IdentityTestCase;

class AccountAdministrationTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_2_new_person_and_account_are_created_atomically_with_one_time_password(): void
    {
        $actor = $this->actorWithRole('direction');
        $lastAuditId = (int) (AuditLog::query()->max('id') ?? 0);

        $result = app(AccountAdministrationService::class)->create($actor, [
            'person_mode' => 'new',
            'full_name' => 'Mariam Issoufou',
            'first_seen_at' => '2026-07-20',
            'phone' => '+22790123456',
            'roles' => ['finance'],
        ]);
        $user = $result['user']->fresh();
        $audits = AuditLog::query()->where('id', '>', $lastAuditId)->orderBy('id')->get();
        $serializedAudit = $audits->map(static fn (AuditLog $audit): string => json_encode([
            $audit->old_values,
            $audit->new_values,
        ], JSON_THROW_ON_ERROR))->implode('\n');

        $this->assertSame(32, strlen($result['temporary_password']));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $result['temporary_password']);
        $this->assertTrue(Hash::check($result['temporary_password'], $user->password));
        $this->assertSame(UserState::Actif, $user->state);
        $this->assertTrue($user->must_change_password);
        $this->assertSame('Mariam Issoufou', $user->person->full_name);
        $this->assertSame(['finance'], $user->getRoleNames()->all());
        $this->assertSame(['person_created', 'user_created', 'user_roles_changed'], $audits->pluck('action')->all());
        $this->assertTrue($audits->every(fn (AuditLog $audit): bool => $audit->actor_id === $actor->getKey()));
        $this->assertStringNotContainsString($result['temporary_password'], $serializedAudit);
        $this->assertStringNotContainsStringIgnoringCase('password', $serializedAudit);
    }

    public function test_ac_2_returning_person_receives_a_new_account_on_the_existing_person_record(): void
    {
        $actor = $this->actorWithRole('direction');
        $person = Person::factory()->create(['full_name' => 'Personne de retour']);
        $oldAccount = User::factory()->archived()->for($person)->create();
        $personCount = Person::query()->count();

        $result = app(AccountAdministrationService::class)->create($actor, [
            'person_mode' => 'existing',
            'person_id' => $person->getKey(),
            'phone' => $oldAccount->phone,
            'roles' => ['employe'],
        ]);

        $this->assertSame($personCount, Person::query()->count());
        $this->assertSame($person->getKey(), $result['user']->person_id);
        $this->assertSame(2, $person->users()->count());
        $this->assertEqualsCanonicalizing(
            [$oldAccount->getKey(), $result['user']->getKey()],
            $person->users()->pluck('id')->all(),
        );
    }

    public function test_task_0_third_direction_guard_rolls_back_the_whole_account_creation(): void
    {
        $actor = $this->actorWithRole('direction');
        $second = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($second, 'direction', $actor->getKey(), 'Direction test');
        $before = [Person::query()->count(), User::query()->count(), AuditLog::query()->count()];

        try {
            app(AccountAdministrationService::class)->create($actor, [
                'person_mode' => 'new',
                'full_name' => 'Troisième direction',
                'first_seen_at' => '2026-07-20',
                'phone' => '+22790909090',
                'roles' => ['direction'],
            ]);
            $this->fail('Le troisième compte direction devait être refusé.');
        } catch (RoleAssignmentConflict) {
            $this->assertSame($before, [
                Person::query()->count(),
                User::query()->count(),
                AuditLog::query()->count(),
            ]);
        }
    }

    public function test_ac_3_index_data_obeys_visible_to_scope_even_when_called_outside_the_controller(): void
    {
        $employee = $this->actorWithRole('employe');
        User::factory()->active()->count(2)->create();

        $data = app(AccountAdministrationService::class)->indexData($employee, []);

        $this->assertSame(1, $data['accounts']->total());
        $this->assertSame($employee->getKey(), $data['accounts']->items()[0]['id']);
    }

    private function actorWithRole(string $role): User
    {
        $actor = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($actor, $role, null, 'Test story 2.7');

        return $actor;
    }
}
