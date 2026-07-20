<?php

namespace Tests\Feature;

use App\Models\Platform\AuditLog;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuditTestCase;

class AuditLogSchemaTest extends AuditTestCase
{
    public function test_ac_1_audit_log_carries_every_required_raw_value(): void
    {
        $this->assertTrue($this->migrationSchema()->hasColumns('audit_logs', [
            'id',
            'actor_id',
            'actor_label',
            'occurred_at',
            'auditable_type',
            'auditable_id',
            'action',
            'old_values',
            'new_values',
            'reason',
            'ip_address',
            'user_agent',
            'request_id',
        ]));

        $auditLog = AuditLog::factory()->create([
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'approved'],
        ]);

        $this->assertNull($auditLog->actor_id);
        $this->assertSame(['status' => 'draft'], $auditLog->old_values);
        $this->assertSame(['status' => 'approved'], $auditLog->new_values);
        $this->assertSame('UTC', $auditLog->occurred_at->getTimezone()->getName());
        $this->assertIsString($auditLog->request_id);
    }

    public function test_ac_7_required_lookup_indexes_are_present_in_order(): void
    {
        $indexes = collect($this->migrationSchema()->getIndexes('audit_logs'))
            ->keyBy('name');

        $this->assertSame(
            ['auditable_type', 'auditable_id'],
            $indexes->get('audit_logs_auditable_lookup_index')['columns'] ?? null,
        );
        $this->assertSame(
            ['actor_id', 'occurred_at'],
            $indexes->get('audit_logs_actor_occurred_index')['columns'] ?? null,
        );
        $this->assertSame(
            ['occurred_at'],
            $indexes->get('audit_logs_occurred_at_index')['columns'] ?? null,
        );
    }

    public function test_ac_1_mysql_uses_millisecond_json_and_binary_types(): void
    {
        $this->requireMysqlProof();

        $columns = collect(DB::connection($this->migrationConnectionName())
            ->select('SHOW COLUMNS FROM audit_logs'))
            ->keyBy('Field');

        // ⛔ DATETIME(3) obligatoire : TIMESTAMP plafonne en 2038 et se convertit selon le fuseau
        // de session MySQL. Sur une table en rétention permanente, les deux sont disqualifiants.
        $this->assertSame('datetime(3)', $columns->get('occurred_at')->Type ?? null);
        $this->assertSame('json', $columns->get('old_values')->Type ?? null);
        $this->assertSame('json', $columns->get('new_values')->Type ?? null);
        $this->assertSame('varbinary(16)', $columns->get('ip_address')->Type ?? null);
    }

    public function test_ac_1_audit_migration_precedes_every_business_model(): void
    {
        $migrations = collect(glob(database_path('migrations/*.php')) ?: []);
        $auditMigration = $migrations
            ->first(fn (string $path): bool => str_contains($path, 'create_audit_logs_table'));
        $peopleMigration = $migrations
            ->first(fn (string $path): bool => str_contains($path, 'create_people_table'));
        $usersMigration = $migrations
            ->first(fn (string $path): bool => str_contains($path, 'create_users_table'));

        $this->assertIsString($auditMigration);
        $this->assertIsString($peopleMigration);
        $this->assertIsString($usersMigration);
        $this->assertLessThan($peopleMigration, $auditMigration);
        $this->assertLessThan($usersMigration, $peopleMigration);
        $this->assertFileDoesNotExist(app_path('Models/User.php'));
        $this->assertTrue($this->migrationSchema()->hasTable('people'));
        $this->assertTrue($this->migrationSchema()->hasTable('users'));
    }

    public function test_ac_3_sqlite_limitations_and_mysql_barriers_are_documented(): void
    {
        $documentation = file_get_contents(base_path('docs/ops/audit-log.md'));

        $this->assertIsString($documentation);
        $this->assertStringContainsString('SQLite ne constitue jamais une preuve', $documentation);
        $this->assertStringContainsString('log_bin_trust_function_creators=1', $documentation);
        $this->assertStringContainsString('AUDIT_DB_APP_USERNAME', $documentation);
    }
}
