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

class IdentityAuditTest extends IdentityTestCase
{
    public function test_ac_7_explicit_creation_update_and_state_changes_are_audited_once(): void
    {
        $service = app(IdentityService::class);
        $person = $service->createPerson(
            ['full_name' => 'Aïcha Amadou', 'first_seen_at' => '2025-06-01'],
            null,
            'Direction test',
            'Création de la fiche',
        );
        $service->updatePerson(
            $person,
            ['full_name' => 'Aïcha Amadou Issa'],
            null,
            'Direction test',
            'Nom complet corrigé',
        );
        $service->changePersonStatus(
            $person,
            PersonOperationalStatus::Absent,
            null,
            'Direction test',
            'Absence planifiée',
        );
        $user = $service->createUser(
            $person,
            [
                'phone' => '90 00 11 22',
                'password' => 'Mot-De-Passe-Initial-2026',
                'state' => UserState::Invite,
            ],
            null,
            'Direction test',
            'Invitation initiale',
        );
        $service->updateUser(
            $user,
            ['phone' => '90 00 11 23', 'password' => 'Mot-De-Passe-Renouvele-2026'],
            null,
            'Direction test',
            'Coordonnées renouvelées',
        );
        $service->changeUserState(
            $user,
            UserState::Actif,
            null,
            'Direction test',
            'Invitation acceptée',
        );

        foreach ([
            'person_created',
            'person_updated',
            'person_status_changed',
            'user_created',
            'user_updated',
            'user_state_changed',
        ] as $action) {
            $this->assertSame(1, AuditLog::query()->where('action', $action)->count(), $action);
        }

        $this->assertSame(0, AuditLog::query()
            ->whereIn('action', ['created', 'updated'])
            ->whereIn('auditable_type', [$person->getMorphClass(), $user->getMorphClass()])
            ->count());
    }

    public function test_ac_7_password_is_never_written_by_explicit_or_fallback_audit(): void
    {
        $person = Person::factory()->create();
        $serviceUser = app(IdentityService::class)->createUser(
            $person,
            ['phone' => '+22793000001', 'password' => 'Secret-Explicite-2026'],
            null,
            'Direction test',
        );
        app(IdentityService::class)->changePassword(
            $serviceUser,
            'Secret-Modifie-2026',
            null,
            'Direction test',
            'Renouvellement du secret',
        );
        User::factory()->for($person)->create([
            'phone' => '+22793000002',
            'password' => 'Secret-Filet-2026',
        ]);

        $serializedAudit = AuditLog::query()
            ->get(['old_values', 'new_values'])
            ->map(fn (AuditLog $audit): string => json_encode([
                $audit->old_values,
                $audit->new_values,
            ], JSON_THROW_ON_ERROR))
            ->implode('\n');

        $this->assertStringNotContainsStringIgnoringCase('password', $serializedAudit);
        $this->assertStringNotContainsString('Secret-Explicite-2026', $serializedAudit);
        $this->assertStringNotContainsString('Secret-Modifie-2026', $serializedAudit);
        $this->assertStringNotContainsString('Secret-Filet-2026', $serializedAudit);
    }

    public function test_ac_7_audit_failure_rolls_back_identity_creation(): void
    {
        $this->requireMysqlProof();

        $migration = DB::connection($this->migrationConnectionName());
        $migration->unprepared('DROP TRIGGER IF EXISTS audit_logs_reject_identity_for_test');
        $migration->unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_reject_identity_for_test
            BEFORE INSERT ON audit_logs
            FOR EACH ROW
            BEGIN
                IF NEW.action = 'person_created' THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'forced identity audit failure';
                END IF;
            END
            SQL);

        try {
            try {
                app(IdentityService::class)->createPerson(
                    ['full_name' => 'Fiche à annuler', 'first_seen_at' => '2026-07-20'],
                    null,
                    'Direction test',
                );
                $this->fail("L'échec d'audit devait annuler la création.");
            } catch (QueryException $exception) {
                $this->assertSame(1644, $exception->errorInfo[1] ?? null);
            }

            $this->assertDatabaseMissing('people', ['full_name' => 'Fiche à annuler']);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'person_created']);
        } finally {
            // Le test englobant utilise une transaction applicative. La tentative d'INSERT garde
            // un verrou de métadonnées MySQL jusqu'au rollback ; le libérer avant DROP TRIGGER
            // évite que la connexion de migration attende indéfiniment ce verrou.
            while (DB::connection()->transactionLevel() > 0) {
                DB::connection()->rollBack();
            }

            $migration->unprepared('DROP TRIGGER IF EXISTS audit_logs_reject_identity_for_test');
        }
    }
}
