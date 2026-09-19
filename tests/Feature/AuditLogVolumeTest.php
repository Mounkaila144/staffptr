<?php

namespace Tests\Feature;

use App\Models\Platform\AuditLog;
use Carbon\CarbonImmutable;
use Tests\Support\IdentityTestCase;

class AuditLogVolumeTest extends IdentityTestCase
{
    public function test_ac_6_mysql_indexes_cover_all_four_filters_and_combinations_on_one_hundred_thousand_entries(): void
    {
        $this->requireMysqlProof();

        $indexes = collect($this->migrationSchema()->getIndexes('audit_logs'))->keyBy('name');
        $this->assertSame(['actor_id', 'occurred_at'], $indexes->get('audit_logs_actor_occurred_index')['columns'] ?? null);
        $this->assertSame(['occurred_at'], $indexes->get('audit_logs_occurred_at_index')['columns'] ?? null);
        $this->assertSame(['action'], $indexes->get('audit_logs_action_index')['columns'] ?? null);
        $this->assertSame(['auditable_type', 'occurred_at'], $indexes->get('audit_logs_type_occurred_index')['columns'] ?? null);

        $start = CarbonImmutable::parse('2025-08-01 00:00:00', 'UTC');

        for ($offset = 0; $offset < 100_000; $offset += 1_000) {
            $batch = [];

            for ($index = $offset; $index < $offset + 1_000; $index++) {
                $target = $index % 1_000 === 0;
                $batch[] = [
                    'actor_id' => $target ? 42 : (($index % 50) + 1),
                    'actor_label' => $target ? 'Direction volumétrie' : 'Auteur '.($index % 50),
                    'occurred_at' => $start->addDays($index % 365)->format('Y-m-d H:i:s.v'),
                    'auditable_type' => $target ? 'tests.target' : 'tests.fixture.'.($index % 20),
                    'auditable_id' => $index + 1,
                    'action' => $target ? 'target_action' : 'action_'.($index % 30),
                    'old_values' => null,
                    'new_values' => json_encode(['sequence' => $index], JSON_THROW_ON_ERROR),
                    'reason' => null,
                    'ip_address' => null,
                    'user_agent' => 'PHPUnit volume',
                    'request_id' => "volume-{$index}",
                ];
            }

            AuditLog::query()->insert($batch);
        }

        $startedAt = microtime(true);
        $queries = [
            AuditLog::query()->where('actor_id', 42)->whereBetween('occurred_at', ['2026-01-01', '2026-01-31 23:59:59']),
            AuditLog::query()->whereBetween('occurred_at', ['2026-01-01', '2026-01-31 23:59:59']),
            AuditLog::query()->where('auditable_type', 'tests.target'),
            AuditLog::query()->where('action', 'target_action'),
            AuditLog::query()->where('auditable_type', 'tests.target')->whereBetween('occurred_at', ['2026-01-01', '2026-01-31 23:59:59']),
            AuditLog::query()
                ->where('actor_id', 42)
                ->whereBetween('occurred_at', ['2026-01-01', '2026-01-31 23:59:59'])
                ->where('auditable_type', 'tests.target')
                ->where('action', 'target_action'),
        ];

        foreach ($queries as $query) {
            $query->count();
            $query->orderByDesc('occurred_at')->limit(25)->get();
        }

        $this->assertLessThan(
            3.0,
            microtime(true) - $startedAt,
            'Les quatre filtres et leurs combinaisons doivent rester sous trois secondes sur 100 000 entrées.',
        );
        $this->assertSame(100_000, AuditLog::query()->where('request_id', 'like', 'volume-%')->count());
    }
}
