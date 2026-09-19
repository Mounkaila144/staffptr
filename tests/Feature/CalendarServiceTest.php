<?php

namespace Tests\Feature;

use App\Models\Platform\Holiday;
use App\Services\Platform\CalendarService;
use Tests\Support\IdentityTestCase;

class CalendarServiceTest extends IdentityTestCase
{
    public function test_ac_1_working_days_default_to_monday_through_friday_and_remain_configurable(): void
    {
        $calendar = app(CalendarService::class);

        $this->assertSame(
            ['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'],
            $calendar->expectedWorkingDays('2026-09-07', '2026-09-13'),
        );

        $this->setSetting('working_days', ['sam']);

        $this->assertSame(['2026-09-12'], $calendar->expectedWorkingDays('2026-09-07', '2026-09-13'));
    }

    public function test_ac_3_is_working_day_covers_weekday_saturday_sunday_and_an_active_holiday(): void
    {
        Holiday::factory()->create(['label' => 'Fermeture test', 'date' => '2026-09-09']);
        $calendar = app(CalendarService::class);

        $this->assertTrue($calendar->isWorkingDay('2026-09-08'));
        $this->assertFalse($calendar->isWorkingDay('2026-09-12'));
        $this->assertFalse($calendar->isWorkingDay('2026-09-13'));
        $this->assertFalse($calendar->isWorkingDay('2026-09-09'));
    }

    public function test_ac_3_an_inactive_holiday_does_not_close_the_company(): void
    {
        Holiday::factory()->inactive()->create(['date' => '2026-09-09']);

        $this->assertTrue(app(CalendarService::class)->isWorkingDay('2026-09-09'));
    }

    public function test_ac_4_period_contract_excludes_every_non_working_day_from_expected_reports(): void
    {
        Holiday::factory()->create(['label' => 'Fermeture test', 'date' => '2026-09-09']);
        $calendar = app(CalendarService::class);

        $this->assertSame(
            ['2026-09-07', '2026-09-08', '2026-09-10', '2026-09-11'],
            $calendar->expectedWorkingDays('2026-09-07', '2026-09-13'),
        );
        $this->assertSame(
            ['2026-09-09', '2026-09-12', '2026-09-13'],
            $calendar->nonWorkingDays('2026-09-07', '2026-09-13'),
        );
    }
}
