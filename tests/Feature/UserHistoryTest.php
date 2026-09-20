<?php

namespace Tests\Feature;

use App\Enums\PersonOperationalStatus;
use App\Models\Identity\Department;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Identity\UserHistory;
use App\Models\Platform\AuditLog;
use App\Services\Identity\IdentityService;
use App\Services\Identity\PersonProfileService;
use App\Services\Identity\RoleAssignmentService;
use App\Services\Identity\UserHistoryService;
use App\Support\Auditing\AuditLogger;
use App\Support\Auditing\ImmutableRecordException;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\Support\IdentityTestCase;

class UserHistoryTest extends IdentityTestCase
{
    private bool $committedProofRan = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        if ($this->committedProofRan) {
            // La transaction du test est refermée d'abord : le `migrate:fresh` qui suit
            // réclame un verrou de métadonnées exclusif, qu'une transaction encore ouverte
            // sur la connexion applicative ferait attendre indéfiniment.
            DB::connection()->rollBack();
            $this->restoreSchemaAfterCommittedProof();
        }

        parent::tearDown();
    }

    public function test_ac_1_and_3_four_business_changes_create_readable_history_and_distinct_audit_entries(): void
    {
        $actor = $this->namedUser('Aminata Direction');
        $oldManager = $this->namedUser('Aïcha Issa');
        $newManager = $this->namedUser('Moussa Adamou');
        $oldDepartment = Department::factory()->create(['name' => 'Conseil']);
        $newDepartment = Department::factory()->create(['name' => 'Technique']);
        $target = User::factory()->active()->create([
            'department_id' => $oldDepartment->getKey(),
            'manager_id' => $oldManager->getKey(),
        ]);

        app(RoleAssignmentService::class)->assignRole(
            $target,
            'employe',
            $actor->getKey(),
            'Aminata Direction',
            'Affectation initiale',
        );
        app(PersonProfileService::class)->update(
            $target->person,
            $target,
            $this->profilePayload($target, $newDepartment->getKey(), $newManager->getKey()),
            $actor,
        );
        app(IdentityService::class)->changePersonStatus(
            $target->person->fresh(),
            PersonOperationalStatus::Absent,
            $actor->getKey(),
            'Aminata Direction',
            'Congé validé',
        );

        $history = UserHistory::query()
            ->where('user_id', $target->getKey())
            ->get()
            ->keyBy('field');

        $this->assertCount(4, $history);
        $this->assertHistory($history->get('roles'), 'Aucun rôle', 'Employé', $actor);
        $this->assertHistory($history->get('department_id'), 'Conseil', 'Technique', $actor);
        $this->assertHistory($history->get('manager_id'), 'Aïcha Issa', 'Moussa Adamou', $actor);
        $this->assertHistory($history->get('operational_status'), 'Actif', 'Absent', $actor);
        $this->assertSame('Congé validé', $history->get('operational_status')?->reason);

        $this->assertTrue(AuditLog::query()->where('action', 'user_role_assigned')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'user_profile_updated')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'manager_changed')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'person_status_changed')->exists());
        $this->assertSame('user_history', (new UserHistory)->getTable());
        $this->assertSame('audit_logs', (new AuditLog)->getTable());
    }

    public function test_ac_1_unchanged_values_do_not_create_x_to_x_history(): void
    {
        $actor = $this->namedUser('Direction Test');
        $department = Department::factory()->create(['name' => 'Finance']);
        $manager = $this->namedUser('Responsable Test');
        $target = User::factory()->active()->create([
            'department_id' => $department->getKey(),
            'manager_id' => $manager->getKey(),
        ]);
        $roles = app(RoleAssignmentService::class);
        $profiles = app(PersonProfileService::class);
        $identity = app(IdentityService::class);

        $roles->assignRole($target, 'employe', $actor->getKey(), 'Direction Test');
        $before = UserHistory::query()->where('user_id', $target->getKey())->count();

        $roles->syncRoles($target, ['employe'], $actor->getKey(), 'Direction Test');
        $profiles->update(
            $target->person,
            $target,
            $this->profilePayload($target, $department->getKey(), $manager->getKey()),
            $actor,
        );
        $identity->changePersonStatus(
            $target->person->fresh(),
            PersonOperationalStatus::Actif,
            $actor->getKey(),
            'Direction Test',
            'Sans changement',
        );

        $this->assertSame($before, UserHistory::query()->where('user_id', $target->getKey())->count());
        $this->assertFalse(UserHistory::query()->whereColumn('old_value', 'new_value')->exists());
    }

    public function test_ac_2_presentation_is_newest_first_in_french_with_author_and_system_fallback(): void
    {
        $target = User::factory()->active()->create();
        $actor = $this->namedUser('Aïcha Admin');

        UserHistory::factory()->create([
            'user_id' => $target->getKey(),
            'field' => 'manager_id',
            'old_value' => 'Aïcha',
            'new_value' => 'Moussa',
            'changed_by' => $actor->getKey(),
            'changed_at' => CarbonImmutable::parse('2026-07-20 08:15:00', 'UTC'),
        ]);
        UserHistory::factory()->create([
            'user_id' => $target->getKey(),
            'field' => 'operational_status',
            'old_value' => 'Actif',
            'new_value' => 'Absent',
            'changed_by' => null,
            'changed_at' => CarbonImmutable::parse('2026-07-20 09:15:00', 'UTC'),
        ]);

        $display = app(UserHistoryService::class)->forDisplay($target);

        $this->assertSame('Statut', $display['data'][0]['field_label']);
        $this->assertSame('Actif', $display['data'][0]['old_value']);
        $this->assertSame('Absent', $display['data'][0]['new_value']);
        $this->assertSame('Système', $display['data'][0]['author']);
        $this->assertSame('20/07/2026 10:15', $display['data'][0]['changed_at']);
        $this->assertSame('Responsable', $display['data'][1]['field_label']);
        $this->assertSame('Aïcha', $display['data'][1]['old_value']);
        $this->assertSame('Moussa', $display['data'][1]['new_value']);
        $this->assertSame('Aïcha Admin', $display['data'][1]['author']);
    }

    public function test_ac_3_history_failure_rolls_back_the_business_change_and_its_audit(): void
    {
        $target = User::factory()->active()->create();
        $history = Mockery::mock(UserHistoryService::class)->makePartial();
        $history->shouldReceive('record')->once()->andThrow(new RuntimeException('Écriture indisponible'));
        $service = new RoleAssignmentService(app(AuditLogger::class), $history);

        try {
            $service->assignRole($target, 'employe', null, 'Test transaction');
            $this->fail("L'échec de l'historique devait annuler la transaction.");
        } catch (RuntimeException $exception) {
            $this->assertSame('Écriture indisponible', $exception->getMessage());
        }

        $this->assertDatabaseMissing('model_has_roles', [
            'model_type' => $target->getMorphClass(),
            'model_id' => $target->getKey(),
        ]);
        $this->assertFalse(AuditLog::query()->where('action', 'user_role_assigned')->exists());
        $this->assertFalse(UserHistory::query()->where('user_id', $target->getKey())->exists());
    }

    public function test_ac_4_model_guard_refuses_update_save_and_delete(): void
    {
        foreach (['update', 'save', 'delete'] as $operation) {
            $entry = UserHistory::factory()->create(['old_value' => "Original {$operation}"]);

            try {
                if ($operation === 'update') {
                    $entry->update(['old_value' => 'Altéré']);
                } elseif ($operation === 'save') {
                    $entry->old_value = 'Altéré';
                    $entry->save();
                } else {
                    $entry->delete();
                }

                $this->fail("La garde modèle devait refuser {$operation}.");
            } catch (ImmutableRecordException) {
                $this->assertDatabaseHas('user_history', [
                    'id' => $entry->getKey(),
                    'old_value' => "Original {$operation}",
                ]);
            }
        }
    }

    public function test_ac_4_mysql_triggers_refuse_privileged_update_and_delete(): void
    {
        $this->requireMysqlProof();

        $connection = DB::connection($this->migrationConnectionName());

        // La ligne visée doit être committée. Créée par la connexion applicative, elle
        // resterait enfermée dans la transaction du test : le compte privilégié, qui n'y
        // participe pas, attendrait un verrou (1205) au lieu de se heurter au déclencheur
        // (1644), et la preuve passerait à côté de son sujet. Ses parents doivent l'être
        // aussi, sinon la contrainte de clé étrangère attend à son tour.
        $this->committedProofRan = true;
        $personId = $connection->table('people')->insertGetId(
            Person::factory()->make()->getAttributes()
        );
        $userId = $connection->table('users')->insertGetId(
            User::factory()->make(['person_id' => $personId])->getAttributes()
        );
        $entryId = $connection->table('user_history')->insertGetId(
            UserHistory::factory()->make([
                'user_id' => $userId,
                'changed_by' => $userId,
                'old_value' => 'Déclencheur original',
            ])->getAttributes()
        );

        foreach (['UPDATE', 'DELETE'] as $operation) {
            try {
                $query = $connection->table('user_history')->where('id', $entryId);
                $operation === 'UPDATE'
                    ? $query->update(['old_value' => 'Altéré'])
                    : $query->delete();
                $this->fail("Le déclencheur devait refuser {$operation}.");
            } catch (QueryException $exception) {
                $this->assertSame(1644, $exception->errorInfo[1] ?? null);
            }

            $this->assertSame('Déclencheur original', $connection->table('user_history')
                ->where('id', $entryId)
                ->value('old_value'));
        }
    }

    public function test_ac_4_application_privileges_are_select_insert_only(): void
    {
        $this->requireMysqlProof();

        $entry = UserHistory::factory()->create(['old_value' => 'Privilège original']);
        $connection = DB::connection();

        $this->assertSame(
            'Privilège original',
            $connection->table('user_history')->where('id', $entry->getKey())->value('old_value'),
        );

        foreach (['UPDATE', 'DELETE'] as $operation) {
            try {
                $query = $connection->table('user_history')->where('id', $entry->getKey());
                $operation === 'UPDATE'
                    ? $query->update(['old_value' => 'Altéré'])
                    : $query->delete();
                $this->fail("Le privilège applicatif devait refuser {$operation}.");
            } catch (QueryException $exception) {
                $this->assertSame(1142, $exception->errorInfo[1] ?? null);
            }
        }

        $grants = $connection->select('SHOW GRANTS');
        $grantSql = implode("\n", array_map(
            fn (object $grant): string => implode(' ', (array) $grant),
            $grants,
        ));
        $historyGrant = collect(explode("\n", $grantSql))
            ->first(fn (string $grant): bool => str_contains($grant, '.`user_history`'));

        $this->assertIsString($historyGrant);
        $this->assertStringContainsString('SELECT, INSERT', $historyGrant);
        $this->assertStringNotContainsString('UPDATE', $historyGrant);
        $this->assertStringNotContainsString('DELETE', $historyGrant);
    }

    private function namedUser(string $name): User
    {
        return User::factory()->active()->create([
            'person_id' => Person::factory()->state(['full_name' => $name]),
        ]);
    }

    /** @return array<string, mixed> */
    private function profilePayload(User $user, ?int $departmentId, ?int $managerId): array
    {
        return [
            'full_name' => $user->person->full_name,
            'phone' => $user->phone,
            'department_id' => $departmentId,
            'job_function_id' => $user->job_function_id,
            'manager_id' => $managerId,
            'relation_type' => $user->relation_type->value,
            'contract_start_date' => $user->contract_start_date?->toDateString(),
            'contract_end_date' => $user->contract_end_date?->toDateString(),
        ];
    }

    private function assertHistory(?UserHistory $history, string $old, string $new, User $actor): void
    {
        $this->assertInstanceOf(UserHistory::class, $history);
        $this->assertSame($old, $history->old_value);
        $this->assertSame($new, $history->new_value);
        $this->assertSame($actor->getKey(), $history->changed_by);
        $this->assertInstanceOf(CarbonImmutable::class, $history->changed_at);
    }
}
