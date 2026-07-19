<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\Support\UsesSeparatedDatabaseConnections;
use Tests\TestCase;

class MigrationFoundationTest extends TestCase
{
    use UsesSeparatedDatabaseConnections;

    public function test_ac_1_fresh_migrations_only_create_queue_infrastructure(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', [
            '--database' => $this->migrationConnectionName(),
            '--force' => true,
        ]));

        $this->assertTrue($this->migrationSchema()->hasTable('jobs'));
        $this->assertTrue($this->migrationSchema()->hasTable('job_batches'));
        $this->assertTrue($this->migrationSchema()->hasTable('failed_jobs'));
        $this->assertTrue($this->migrationSchema()->hasTable('audit_logs'));
        $this->assertFalse($this->migrationSchema()->hasTable('users'));
        $this->assertFalse($this->migrationSchema()->hasTable('sessions'));
        $this->assertFalse($this->migrationSchema()->hasTable('cache'));
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
