<?php

namespace Tests\Feature;

use App\Models\Platform\AuditLog;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Models\AuditedRecord;
use Tests\Support\AuditTestCase;

class AuditTransactionTest extends AuditTestCase
{
    public function test_ac_2_business_operation_and_explicit_audit_commit_together(): void
    {
        $logger = app(AuditLogger::class);
        $record = new AuditedRecord(['name' => 'Opération nominale']);
        $requestId = 'transaction-nominal-test';

        DB::connection()->transaction(function () use ($logger, $record, $requestId): void {
            $logger->runExplicitly(
                auditable: $record,
                operation: fn (): bool => $record->save(),
                actorId: null,
                actorLabel: 'Amorçage système',
                action: 'created_explicitly',
                oldValues: null,
                newValues: ['name' => 'Opération nominale'],
                reason: 'Preuve transactionnelle',
                requestId: $requestId,
            );
        });

        $this->assertDatabaseHas('audited_records', ['name' => 'Opération nominale']);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $record->getKey(),
            'action' => 'created_explicitly',
            'request_id' => $requestId,
        ]);
        $this->assertSame(1, AuditLog::query()->where('request_id', $requestId)->count());
    }

    public function test_rule_11_audit_write_failure_rolls_back_the_business_operation(): void
    {
        $this->requireMysqlProof();

        $migration = DB::connection($this->migrationConnectionName());
        $migration->unprepared('DROP TRIGGER IF EXISTS audit_logs_reject_insert_for_test');
        $migration->unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_reject_insert_for_test
            BEFORE INSERT ON audit_logs
            FOR EACH ROW
            BEGIN
                IF NEW.action = 'forced_failure' THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'forced audit failure';
                END IF;
            END
            SQL);

        try {
            $failed = false;
            $record = new AuditedRecord(['name' => 'À annuler']);

            try {
                DB::connection()->transaction(function () use ($record): void {
                    app(AuditLogger::class)->runExplicitly(
                        auditable: $record,
                        operation: fn (): bool => $record->save(),
                        actorId: null,
                        actorLabel: 'Test bloquant',
                        action: 'forced_failure',
                        requestId: 'transaction-failure-test',
                    );
                });
            } catch (QueryException $exception) {
                $failed = true;
                $this->assertSame(1644, $exception->errorInfo[1] ?? null);
            }

            $this->assertTrue($failed, "L'écriture d'audit forcée devait échouer.");
            $this->assertDatabaseMissing('audited_records', ['name' => 'À annuler']);
            $this->assertDatabaseMissing('audit_logs', ['request_id' => 'transaction-failure-test']);
        } finally {
            $migration->unprepared('DROP TRIGGER IF EXISTS audit_logs_reject_insert_for_test');
        }
    }
}
