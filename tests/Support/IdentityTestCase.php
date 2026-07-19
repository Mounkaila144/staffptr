<?php

namespace Tests\Support;

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

        if (! $this->migrationSchema()->hasTable('users')) {
            $exitCode = Artisan::call('migrate', [
                '--database' => $this->migrationConnectionName(),
                '--force' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());
        }
    }
}
