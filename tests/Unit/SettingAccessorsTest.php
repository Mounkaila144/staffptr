<?php

namespace Tests\Unit;

use App\Services\Platform\SettingsService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SettingAccessorsTest extends TestCase
{
    public function test_ac_1_central_schema_declares_every_typed_setting(): void
    {
        $this->assertSame([
            'working_days',
            'report_deadline_time',
            'report_reminder_minutes',
            'intern_limit_per_tutor',
            'reserve_percentage',
            'reserve_target_months',
            'attachment_allowed_types',
            'attachment_max_size_bytes',
            'login_max_failed_attempts',
            'login_lockout_minutes',
            'contract_end_warning_days',
        ], SettingsService::keys());
        $this->assertSame('array', SettingsService::type('working_days'));
        $this->assertSame('time', SettingsService::type('report_deadline_time'));
        $this->assertSame('percentage', SettingsService::type('reserve_percentage'));
        $this->assertContains('integer', SettingsService::valueRules('login_max_failed_attempts'));
        $this->assertContains('distinct', SettingsService::itemRules('attachment_allowed_types'));
    }

    public function test_ac_3_unknown_key_fails_loudly_without_a_default(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SettingsService::valueRules('missing_setting');
    }
}
