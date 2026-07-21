<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Models\Platform\Setting;
use App\Services\Identity\ContractEndingService;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\SettingsService;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\Support\IdentityTestCase;

class SettingsServiceTest extends IdentityTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_ac_1_settings_schema_model_casts_uniqueness_and_no_physical_deletion(): void
    {
        $columns = $this->migrationSchema()->getColumnListing('settings');

        foreach (['id', 'key', 'value', 'type', 'effective_at', 'created_at', 'updated_at'] as $column) {
            $this->assertContains($column, $columns);
        }

        $setting = Setting::factory()->create([
            'key' => 'cast_test',
            'value' => ['pdf', 'png'],
            'effective_at' => '2026-07-21 08:30:00',
        ]);
        $this->assertSame(['pdf', 'png'], $setting->fresh()->value);
        $this->assertInstanceOf(CarbonImmutable::class, $setting->fresh()->effective_at);

        try {
            Setting::factory()->create(['key' => 'cast_test']);
            $this->fail('La clé de paramètre devait être unique.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('unique', mb_strtolower($exception->getMessage()));
        }

        $this->expectException(LogicException::class);
        $setting->delete();
    }

    public function test_ac_1_settings_migration_and_runbook_grant_update_without_delete(): void
    {
        $migration = (string) file_get_contents(database_path(
            'migrations/2026_07_21_051710_create_settings_table.php',
        ));
        $runbook = (string) file_get_contents(base_path('docs/ops/database-users.md'));

        $this->assertStringContainsString('GRANT UPDATE ON', $migration);
        $this->assertStringNotContainsString('GRANT DELETE', $migration);
        $this->assertStringContainsString("config('audit.database.app_username')", $migration);
        $this->assertStringContainsString("config('audit.database.app_host')", $migration);
        $this->assertStringContainsString(
            "GRANT UPDATE ON `ptrstaff_prod`.`settings` TO 'ptrstaff_prod_app'@'localhost';",
            $runbook,
        );
        $this->assertStringContainsString(
            "GRANT UPDATE ON `ptrstaff_staging`.`settings` TO 'ptrstaff_staging_app'@'localhost';",
            $runbook,
        );
    }

    public function test_ac_2_setting_seeder_is_idempotent_with_all_reference_values(): void
    {
        $this->seed(SettingSeeder::class);
        $firstUpdatedAt = Setting::query()
            ->pluck('updated_at', 'key')
            ->map(static fn ($date): string => $date->format('Y-m-d H:i:s.v'));
        $this->seed(SettingSeeder::class);

        $this->assertSame(11, Setting::query()->count());
        $secondUpdatedAt = Setting::query()
            ->pluck('updated_at', 'key')
            ->map(static fn ($date): string => $date->format('Y-m-d H:i:s.v'));
        $this->assertSame($firstUpdatedAt->all(), $secondUpdatedAt->all());
        $this->assertSettingValue('intern_limit_per_tutor', 3);
        $this->assertSettingValue('reserve_percentage', 20);
        $this->assertSettingValue('reserve_target_months', 3);
        $this->assertSettingValue('report_deadline_time', '17:45');
        $this->assertSettingValue('report_reminder_minutes', 60);
        $this->assertSettingValue('attachment_max_size_bytes', 8 * 1024 * 1024);
        $this->assertSettingValue('working_days', ['lun', 'mar', 'mer', 'jeu', 'ven']);
    }

    public function test_ac_3_every_typed_parameter_changes_without_redeployment_and_refreshes_the_cache(): void
    {
        $actor = User::factory()->active()->create();
        $service = app(SettingsService::class);
        $this->assertSame(20, $service->reservePercentage());
        $this->assertTrue(Cache::has(SettingsService::CACHE_KEY));

        $service->update([
            'working_days' => ['lun', 'mar', 'sam'],
            'report_deadline_time' => '16:30',
            'report_reminder_minutes' => 45,
            'intern_limit_per_tutor' => 4,
            'reserve_percentage' => 25,
            'reserve_target_months' => 4,
            'attachment_allowed_types' => ['pdf', 'png'],
            'attachment_max_size_bytes' => 4 * 1024 * 1024,
            'login_max_failed_attempts' => 4,
            'login_lockout_minutes' => 10,
            'contract_end_warning_days' => 14,
        ], CarbonImmutable::parse('2026-07-21 09:00:00', 'UTC'), $actor);

        $this->assertSame(['lun', 'mar', 'sam'], $service->workingDays());
        $this->assertSame('16:30', $service->reportDeadlineTime());
        $this->assertSame(45, $service->reportReminderMinutes());
        $this->assertSame(4, $service->internLimitPerTutor());
        $this->assertSame(25, $service->reservePercentage());
        $this->assertSame(4, $service->reserveTargetMonths());
        $this->assertSame(['pdf', 'png'], $service->attachmentAllowedTypes());
        $this->assertSame(4 * 1024 * 1024, $service->attachmentMaxSizeBytes());
        $this->assertSame(4, $service->loginMaxFailedAttempts());
        $this->assertSame(10, $service->loginLockoutMinutes());
        $this->assertSame(14, $service->contractEndWarningDays());
        $this->assertSame(11, AuditLog::query()->where('action', 'setting_changed')->count());
    }

    public function test_ac_3_contract_ending_behavior_uses_the_new_setting_immediately(): void
    {
        CarbonImmutable::setTestNow('2026-07-21 10:00:00 Africa/Niamey');
        $actor = User::factory()->active()->create();
        $included = User::factory()->active()->endingInDays(7)->create();
        User::factory()->active()->endingInDays(8)->create();

        app(SettingsService::class)->update(
            ['contract_end_warning_days' => 7],
            CarbonImmutable::now('UTC'),
            $actor,
        );

        $this->assertSame([$included->getKey()], app(ContractEndingService::class)->endingWithin()->modelKeys());
        CarbonImmutable::setTestNow();
    }

    public function test_ac_4_each_change_is_audited_with_old_new_value_and_effective_date(): void
    {
        $actor = User::factory()->active()->create();
        $effectiveAt = CarbonImmutable::parse('2026-07-21 10:15:30.123', 'UTC');

        app(SettingsService::class)->update(['reserve_percentage' => 22], $effectiveAt, $actor);

        $audit = AuditLog::query()->where('action', 'setting_changed')->sole();
        $this->assertSame($actor->getKey(), $audit->actor_id);
        $this->assertSame('reserve_percentage', $audit->old_values['key']);
        $this->assertSame(20, $audit->old_values['value']);
        $this->assertSame(22, $audit->new_values['value']);
        $this->assertSame('2026-07-21 10:15:30.123', $audit->new_values['effective_at']);
        $this->assertSame($effectiveAt->timestamp, Setting::query()->where('key', 'reserve_percentage')->sole()->effective_at->timestamp);

        $display = app(AuditLogService::class)->indexData(['action' => 'setting_changed']);
        $entry = $display['entries']['data'][0];
        $changes = collect($entry['changes'])->keyBy('field');
        $this->assertStringStartsWith('Paramètre général #', $entry['object']);
        $this->assertSame('Modification du paramètre', $entry['action']);
        $this->assertSame('Pourcentage de réserve', $changes['key']['new']);
        $this->assertSame("Date d'effet", $changes['effective_at']['label']);
    }

    public function test_ac_4_audit_failure_rolls_back_the_setting_change(): void
    {
        $actor = User::factory()->active()->create();
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('runExplicitly')->once()->andThrow(new RuntimeException('Audit indisponible'));
        $service = new SettingsService($audit);

        try {
            $service->update(['reserve_percentage' => 29], CarbonImmutable::now('UTC'), $actor);
            $this->fail("L'échec d'audit devait annuler la modification.");
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit indisponible', $exception->getMessage());
        }

        $this->assertSettingValue('reserve_percentage', 20);
    }

    public function test_ac_7_next_read_observes_the_new_value_after_cache_invalidation(): void
    {
        $actor = User::factory()->active()->create();
        $service = app(SettingsService::class);
        $this->assertSame(3, $service->internLimitPerTutor());
        $this->assertTrue(Cache::has(SettingsService::CACHE_KEY));

        $service->update(['intern_limit_per_tutor' => 6], CarbonImmutable::now('UTC'), $actor);

        $this->assertFalse(Cache::has(SettingsService::CACHE_KEY));
        $this->assertSame(6, app(SettingsService::class)->internLimitPerTutor());
    }

    private function assertSettingValue(string $key, mixed $value): void
    {
        $this->assertSame($value, Setting::query()->where('key', $key)->sole()->value);
    }
}
