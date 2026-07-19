<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\Support\UsesSeparatedDatabaseConnections;
use Tests\TestCase;

class MigrationFoundationTest extends TestCase
{
    use UsesSeparatedDatabaseConnections;

    /**
     * Les migrations de fondation créent l'infrastructure framework — files, audit et, depuis le
     * Sprint Change Proposal du 19/07/2026, `sessions`, `cache` et `cache_locks` exigés par la
     * matrice de privilèges de la story 1.5 — mais toujours aucune table métier ni `users`.
     */
    public function test_ac_1_fresh_migrations_only_create_framework_infrastructure(): void
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
        $this->assertFalse($this->migrationSchema()->hasTable('users'));
        $this->assertFalse($this->migrationSchema()->hasTable('password_reset_tokens'));
    }

    public function test_ac_1_does_not_keep_the_obsolete_user_scaffold(): void
    {
        $this->assertFileDoesNotExist(app_path('Models/User.php'));
        $this->assertFileDoesNotExist(database_path('factories/UserFactory.php'));

        $authConfiguration = file_get_contents(config_path('auth.php'));
        $this->assertNotFalse($authConfiguration);
        $this->assertStringNotContainsString('User::class', $authConfiguration);
    }
}
