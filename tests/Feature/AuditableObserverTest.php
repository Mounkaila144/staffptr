<?php

namespace Tests\Feature;

use App\Models\Platform\AuditLog;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Models\AuditedRecord;
use Tests\Support\AuditTestCase;

class AuditableObserverTest extends AuditTestCase
{
    public function test_ac_6_observer_records_direct_create_and_update(): void
    {
        $record = AuditedRecord::query()->create(['name' => 'Création contournée']);

        $createdAudit = AuditLog::query()
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->getKey())
            ->where('action', 'created')
            ->firstOrFail();

        $this->assertSame('Système — filet de sécurité', $createdAudit->actor_label);
        $this->assertSame('Création contournée', $createdAudit->new_values['name'] ?? null);

        $record->update(['name' => 'Modification contournée']);

        $updatedAudit = AuditLog::query()
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->getKey())
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame('Création contournée', $updatedAudit->old_values['name'] ?? null);
        $this->assertSame('Modification contournée', $updatedAudit->new_values['name'] ?? null);
    }

    public function test_ac_6_explicit_audit_does_not_create_an_observer_duplicate(): void
    {
        $record = AuditedRecord::query()->create(['name' => 'Avant']);
        $record->name = 'Après';
        $automaticUpdatedBefore = AuditLog::query()
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->getKey())
            ->where('action', 'updated')
            ->count();

        DB::connection()->transaction(function () use ($record): void {
            app(AuditLogger::class)->runExplicitly(
                auditable: $record,
                operation: fn (): bool => $record->save(),
                actorId: 42,
                actorLabel: 'Direction test',
                action: 'renamed_explicitly',
                oldValues: ['name' => 'Avant'],
                newValues: ['name' => 'Après'],
                reason: 'Correction motivée',
                requestId: 'observer-deduplication-test',
            );
        });

        $this->assertSame(1, AuditLog::query()
            ->where('request_id', 'observer-deduplication-test')
            ->count());
        $this->assertSame($automaticUpdatedBefore, AuditLog::query()
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->getKey())
            ->where('action', 'updated')
            ->count());
    }

    public function test_ac_6_observer_failure_rolls_back_a_direct_model_update(): void
    {
        $this->requireMysqlProof();

        $record = AuditedRecord::query()->create(['name' => 'Valeur intacte']);
        $migration = DB::connection($this->migrationConnectionName());
        $migration->unprepared('DROP TRIGGER IF EXISTS audit_logs_reject_observer_for_test');
        $migration->unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_reject_observer_for_test
            BEFORE INSERT ON audit_logs
            FOR EACH ROW
            BEGIN
                IF NEW.action = 'updated' THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'forced observer failure';
                END IF;
            END
            SQL);

        try {
            $failed = false;

            try {
                $record->update(['name' => 'Valeur à annuler']);
            } catch (QueryException $exception) {
                $failed = true;
                $this->assertSame(1644, $exception->errorInfo[1] ?? null);
            }

            $this->assertTrue($failed, "L'échec du filet d'audit devait remonter.");
            $this->assertSame(
                'Valeur intacte',
                AuditedRecord::query()->findOrFail($record->getKey())->name,
            );
        } finally {
            $migration->unprepared('DROP TRIGGER IF EXISTS audit_logs_reject_observer_for_test');
        }
    }
}
