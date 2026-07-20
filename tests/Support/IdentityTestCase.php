<?php

namespace Tests\Support;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

abstract class IdentityTestCase extends TestCase
{
    use DatabaseTransactions;
    use UsesSeparatedDatabaseConnections;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->migrationSchema()->hasTable('users') || ! $this->migrationSchema()->hasTable('roles')) {
            $exitCode = Artisan::call('migrate', [
                '--database' => $this->migrationConnectionName(),
                '--force' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());
        }
    }

    protected function seedRbac(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }
}
