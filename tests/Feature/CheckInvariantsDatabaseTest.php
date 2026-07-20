<?php

namespace Tests\Feature;

use App\Services\Platform\Invariants\AuditDeletePrivilegeInvariant;
use App\Services\Platform\Invariants\AuditTriggersInvariant;
use App\Services\Platform\Invariants\EnvironmentInvariant;
use App\Services\Platform\Invariants\SuperAdminPermissionInvariant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesSeparatedDatabaseConnections;
use Tests\TestCase;

class CheckInvariantsDatabaseTest extends TestCase
{
    use UsesSeparatedDatabaseConnections;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireMysqlProof();
        config(['app.env' => 'testing', 'app.debug' => true]);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_ac_11_and_12_healthy_mysql_server_returns_zero(): void
    {
        foreach ([
            app(EnvironmentInvariant::class),
            app(SuperAdminPermissionInvariant::class),
            app(AuditTriggersInvariant::class),
            app(AuditDeletePrivilegeInvariant::class),
        ] as $invariant) {
            $result = $invariant->check();
            $this->assertTrue(
                $result->passed,
                "{$result->name} — constaté : {$result->observed} — attendu : {$result->expected}",
            );
        }

        $this->artisan('ptr:check-invariants')
            ->expectsOutputToContain('Les quatre invariants sont conformes.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_ac_11_and_12_missing_audit_trigger_is_detected_then_restored(): void
    {
        $migration = DB::connection($this->migrationConnectionName());
        $migration->unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_delete');

        try {
            $this->artisan('ptr:check-invariants')
                ->expectsOutputToContain("Déclencheurs d'immuabilité du journal d'audit")
                ->assertExitCode(Command::FAILURE);
        } finally {
            $migration->unprepared(<<<'SQL'
                CREATE TRIGGER audit_logs_prevent_delete
                BEFORE DELETE ON audit_logs
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'audit_logs entries are immutable'
                SQL);
        }
    }

    public function test_ac_11_and_12_delete_grant_on_audit_log_is_detected_then_revoked(): void
    {
        $this->grantApplicationTablePrivileges('audit_logs', ['DELETE']);

        try {
            $this->artisan('ptr:check-invariants')
                ->expectsOutputToContain("Privilège DELETE du journal d'audit")
                ->assertExitCode(Command::FAILURE);
        } finally {
            $this->revokeApplicationAuditDelete();
        }
    }

    private function revokeApplicationAuditDelete(): void
    {
        $connection = DB::connection($this->migrationConnectionName());
        $username = config('audit.database.app_username');
        $host = config('audit.database.app_host');

        $this->assertIsString($username);
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_]+\z/', $username);
        $this->assertIsString($host);
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_.:%-]+\z/', $host);

        $database = '`'.str_replace('`', '``', $connection->getDatabaseName()).'`';

        $connection->unprepared(
            "REVOKE DELETE ON {$database}.`audit_logs` FROM '{$username}'@'{$host}'"
        );
    }
}
