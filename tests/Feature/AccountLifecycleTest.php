<?php

namespace Tests\Feature;

use App\Enums\PersonOperationalStatus;
use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Identity\IdentityService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

class AccountLifecycleTest extends IdentityTestCase
{
    public function test_ac_1_account_lifecycle_exposes_exactly_five_states(): void
    {
        $this->assertSame(
            ['invite', 'actif', 'suspendu', 'termine', 'archive'],
            array_column(UserState::cases(), 'value'),
        );
    }

    public function test_ac_4_person_status_and_account_state_evolve_independently_in_both_directions(): void
    {
        $person = Person::factory()->create([
            'operational_status' => PersonOperationalStatus::Actif,
        ]);
        $user = User::factory()->for($person)->active()->create();
        $service = app(IdentityService::class);

        $service->changePersonStatus(
            $person,
            PersonOperationalStatus::Absent,
            null,
            'Direction test',
            'Congé planifié',
        );

        $this->assertSame(PersonOperationalStatus::Absent, $person->fresh()->operational_status);
        $this->assertSame(UserState::Actif, $user->fresh()->state);

        $service->changePersonStatus(
            $person,
            PersonOperationalStatus::Actif,
            null,
            'Direction test',
            'Retour de congé',
        );
        $service->changeUserState(
            $user,
            UserState::Suspendu,
            null,
            'Direction test',
            "Retrait disciplinaire de l'accès",
        );

        $this->assertSame(PersonOperationalStatus::Actif, $person->fresh()->operational_status);
        $this->assertSame(UserState::Suspendu, $user->fresh()->state);
    }

    public function test_ac_5_account_state_audit_contains_readable_old_and_new_values(): void
    {
        $user = User::factory()->active()->create();

        app(IdentityService::class)->changeUserState(
            $user,
            UserState::Suspendu,
            null,
            'Direction test',
            "Retrait temporaire de l'accès",
        );

        $audit = AuditLog::query()->where('action', 'user_state_changed')->sole();

        $this->assertSame(['state' => 'actif'], $audit->old_values);
        $this->assertSame([
            'state' => 'suspendu',
            'sessions_revoked' => 0,
        ], $audit->new_values);
    }

    public function test_ac_5_person_status_audit_contains_readable_old_and_new_values(): void
    {
        $person = Person::factory()->create([
            'operational_status' => PersonOperationalStatus::Actif,
        ]);

        app(IdentityService::class)->changePersonStatus(
            $person,
            PersonOperationalStatus::Absent,
            null,
            'Direction test',
            'Congé planifié',
        );

        $audit = AuditLog::query()->where('action', 'person_status_changed')->sole();

        $this->assertSame(['operational_status' => 'actif'], $audit->old_values);
        $this->assertSame(['operational_status' => 'absent'], $audit->new_values);
    }

    public function test_ac_5_audit_failure_rolls_back_suspension_and_session_revocation(): void
    {
        $this->requireMysqlProof();

        $user = User::factory()->active()->create();
        DB::table('sessions')->insert([
            'id' => 'session-preservee-si-audit-echoue',
            'user_id' => $user->getKey(),
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $migration = DB::connection($this->migrationConnectionName());
        $migration->unprepared('DROP TRIGGER IF EXISTS audit_logs_reject_suspension_for_test');
        $migration->unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_reject_suspension_for_test
            BEFORE INSERT ON audit_logs
            FOR EACH ROW
            BEGIN
                IF NEW.action = 'user_state_changed' THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'forced lifecycle audit failure';
                END IF;
            END
            SQL);

        try {
            try {
                app(IdentityService::class)->changeUserState(
                    $user,
                    UserState::Suspendu,
                    null,
                    'Direction test',
                    "Retrait temporaire de l'accès",
                );
                $this->fail("L'échec d'audit devait annuler la suspension.");
            } catch (QueryException $exception) {
                $this->assertSame(1644, $exception->errorInfo[1] ?? null);
            }

            $this->assertSame(UserState::Actif, $user->fresh()->state);
            $this->assertDatabaseHas('sessions', [
                'id' => 'session-preservee-si-audit-echoue',
                'user_id' => $user->getKey(),
            ]);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'user_state_changed']);
        } finally {
            while (DB::connection()->transactionLevel() > 0) {
                DB::connection()->rollBack();
            }

            $migration->unprepared('DROP TRIGGER IF EXISTS audit_logs_reject_suspension_for_test');
        }
    }
}
