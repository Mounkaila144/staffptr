<?php

namespace Tests\Feature;

use App\Models\Platform\AuditLog;
use App\Support\Auditing\ImmutableRecordException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuditTestCase;

class AuditImmutabilityTest extends AuditTestCase
{
    public function test_ac_3_model_guard_refuses_update_save_and_delete(): void
    {
        foreach (['update', 'save', 'delete'] as $operation) {
            $auditLog = AuditLog::factory()->create([
                'actor_label' => "Original {$operation}",
            ]);

            try {
                if ($operation === 'update') {
                    $auditLog->update(['actor_label' => 'Altéré']);
                } elseif ($operation === 'save') {
                    $auditLog->actor_label = 'Altéré';
                    $auditLog->save();
                } else {
                    $auditLog->delete();
                }

                $this->fail("La garde modèle devait refuser {$operation}.");
            } catch (ImmutableRecordException) {
                $fresh = AuditLog::query()->findOrFail($auditLog->getKey());

                $this->assertSame("Original {$operation}", $fresh->actor_label);
            }
        }
    }

    public function test_ac_5_mysql_triggers_refuse_privileged_update_and_delete(): void
    {
        $this->requireMysqlProof();

        $auditLog = AuditLog::factory()->create(['actor_label' => 'Déclencheur original']);
        $connection = DB::connection($this->migrationConnectionName());

        foreach (['UPDATE', 'DELETE'] as $operation) {
            try {
                if ($operation === 'UPDATE') {
                    $connection->table('audit_logs')
                        ->where('id', $auditLog->getKey())
                        ->update(['actor_label' => 'Altéré']);
                } else {
                    $connection->table('audit_logs')
                        ->where('id', $auditLog->getKey())
                        ->delete();
                }

                $this->fail("Le déclencheur devait refuser {$operation}.");
            } catch (QueryException $exception) {
                $this->assertSame(1644, $exception->errorInfo[1] ?? null);
            }

            $this->assertDatabaseHas('audit_logs', [
                'id' => $auditLog->getKey(),
                'actor_label' => 'Déclencheur original',
            ]);
        }
    }

    public function test_ac_5_application_privileges_allow_insert_and_select_but_refuse_mutation(): void
    {
        $this->requireMysqlProof();

        $auditLog = AuditLog::factory()->create(['actor_label' => 'Privilège original']);
        $connection = DB::connection();

        $this->assertSame(
            'Privilège original',
            $connection->table('audit_logs')->where('id', $auditLog->getKey())->value('actor_label'),
        );

        foreach (['UPDATE', 'DELETE'] as $operation) {
            try {
                if ($operation === 'UPDATE') {
                    $connection->table('audit_logs')
                        ->where('id', $auditLog->getKey())
                        ->update(['actor_label' => 'Altéré']);
                } else {
                    $connection->table('audit_logs')
                        ->where('id', $auditLog->getKey())
                        ->delete();
                }

                $this->fail("Le privilège applicatif devait refuser {$operation}.");
            } catch (QueryException $exception) {
                $this->assertSame(1142, $exception->errorInfo[1] ?? null);
            }

            $this->assertDatabaseHas('audit_logs', [
                'id' => $auditLog->getKey(),
                'actor_label' => 'Privilège original',
            ]);
        }
    }

    public function test_ac_3_migration_grants_only_select_and_insert_on_audit_logs(): void
    {
        $this->requireMysqlProof();

        $username = (string) config('audit.database.app_username');
        $host = (string) config('audit.database.app_host');
        $grants = DB::connection($this->migrationConnectionName())
            ->select("SHOW GRANTS FOR '{$username}'@'{$host}'");
        $grantSql = implode("\n", array_map(
            fn (object $grant): string => implode(' ', (array) $grant),
            $grants,
        ));
        $auditGrant = collect(explode("\n", $grantSql))
            ->first(fn (string $grant): bool => str_contains($grant, '.`audit_logs`'));

        $this->assertIsString($auditGrant);
        $this->assertStringContainsString('SELECT, INSERT', $auditGrant);
        $this->assertStringNotContainsString('UPDATE', $auditGrant);
        $this->assertStringNotContainsString('DELETE', $auditGrant);
    }
}
