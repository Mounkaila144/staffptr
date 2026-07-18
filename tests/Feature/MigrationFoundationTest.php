<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationFoundationTest extends TestCase
{
    public function test_ac_1_fresh_migrations_only_create_queue_infrastructure(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));

        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('sessions'));
        $this->assertFalse(Schema::hasTable('cache'));
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
