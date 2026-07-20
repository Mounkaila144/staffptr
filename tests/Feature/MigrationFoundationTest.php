<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesSeparatedDatabaseConnections;
use Tests\TestCase;

class MigrationFoundationTest extends TestCase
{
    use UsesSeparatedDatabaseConnections;

    public function test_ac_1_fresh_migrations_create_identity_after_audit(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', [
            '--database' => $this->migrationConnectionName(),
            '--force' => true,
        ]));

        $this->assertTrue($this->migrationSchema()->hasTable('jobs'));
        $this->assertTrue($this->migrationSchema()->hasTable('job_batches'));
        $this->assertTrue($this->migrationSchema()->hasTable('failed_jobs'));
        $this->assertTrue($this->migrationSchema()->hasTable('audit_logs'));
        $this->assertTrue($this->migrationSchema()->hasTable('sessions'));
        $this->assertTrue($this->migrationSchema()->hasTable('cache'));
        $this->assertTrue($this->migrationSchema()->hasTable('cache_locks'));
        $this->assertTrue($this->migrationSchema()->hasTable('people'));
        $this->assertTrue($this->migrationSchema()->hasTable('users'));
        $this->assertTrue($this->migrationSchema()->hasTable('permissions'));
        $this->assertTrue($this->migrationSchema()->hasTable('roles'));
        $this->assertTrue($this->migrationSchema()->hasTable('model_has_permissions'));
        $this->assertTrue($this->migrationSchema()->hasTable('model_has_roles'));
        $this->assertTrue($this->migrationSchema()->hasTable('role_has_permissions'));
        $this->assertFalse($this->migrationSchema()->hasTable('password_reset_tokens'));

        $migrations = DB::connection($this->migrationConnectionName())
            ->table('migrations')
            ->orderBy('batch')
            ->orderBy('id')
            ->pluck('migration')
            ->all();
        $auditPosition = array_search('2026_07_19_052353_create_audit_logs_table', $migrations, true);
        $peoplePosition = array_search('2026_07_19_230525_create_people_table', $migrations, true);
        $usersPosition = array_search('2026_07_19_230526_create_users_table', $migrations, true);
        $rbacPosition = array_search('2026_07_20_070505_create_permission_tables', $migrations, true);

        $this->assertIsInt($auditPosition);
        $this->assertIsInt($peoplePosition);
        $this->assertIsInt($usersPosition);
        $this->assertIsInt($rbacPosition);
        $this->assertLessThan($peoplePosition, $auditPosition);
        $this->assertLessThan($usersPosition, $peoplePosition);
        $this->assertLessThan($rbacPosition, $usersPosition);
    }

    public function test_ac_1_keeps_the_default_laravel_user_migration_removed(): void
    {
        // Le garde-fou de 1.1 change de forme, pas d'intention : `users` est désormais métier,
        // mais ne doit jamais redevenir l'artefact Laravel créé avant le journal d'audit.
        $this->assertFileDoesNotExist(app_path('Models/User.php'));
        $this->assertFileDoesNotExist(database_path('factories/UserFactory.php'));
        $this->assertFileDoesNotExist(
            database_path('migrations/0001_01_01_000000_create_users_table.php'),
        );

        $files = glob(database_path('migrations/*.php')) ?: [];
        $auditMigration = collect($files)->first(
            fn (string $path): bool => str_contains($path, 'create_audit_logs_table'),
        );
        $peopleMigration = collect($files)->first(
            fn (string $path): bool => str_contains($path, 'create_people_table'),
        );
        $usersMigration = collect($files)->first(
            fn (string $path): bool => str_contains($path, 'create_users_table'),
        );

        $this->assertIsString($auditMigration);
        $this->assertIsString($peopleMigration);
        $this->assertIsString($usersMigration);
        $this->assertLessThan($peopleMigration, $auditMigration);
        $this->assertLessThan($usersMigration, $peopleMigration);
    }
}
