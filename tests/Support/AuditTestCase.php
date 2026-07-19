<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

abstract class AuditTestCase extends TestCase
{
    use UsesSeparatedDatabaseConnections;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->migrationSchema()->hasTable('audit_logs')) {
            $exitCode = Artisan::call('migrate', [
                '--database' => $this->migrationConnectionName(),
                '--force' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());
        }

        $this->migrationSchema()->dropIfExists('audited_records');
        $this->migrationSchema()->create('audited_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->timestamps();
        });
        $this->grantApplicationTablePrivileges(
            'audited_records',
            ['SELECT', 'INSERT', 'UPDATE', 'DELETE'],
        );
    }

    protected function tearDown(): void
    {
        $this->migrationSchema()->dropIfExists('audited_records');

        parent::tearDown();
    }
}
