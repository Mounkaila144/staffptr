<?php

namespace Tests\Feature;

use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Models\Platform\Holiday;
use App\Services\Platform\CalendarService;
use Tests\Support\IdentityTestCase;

class ExpectedReportDaysTest extends IdentityTestCase
{
    public function test_ac_4_approved_absence_excludes_each_covered_working_day(): void
    {
        $user = User::factory()->active()->create();
        Absence::factory()->for($user)->approved()->create(['start_date' => '2026-09-08', 'end_date' => '2026-09-10']);

        $this->assertSame(
            ['2026-09-07', '2026-09-11'],
            app(CalendarService::class)->expectedReportDaysFor($user, '2026-09-07', '2026-09-13'),
        );
    }

    public function test_ac_5_requested_and_refused_absences_do_not_exclude_expected_days(): void
    {
        $user = User::factory()->active()->create();
        Absence::factory()->for($user)->requested()->create(['start_date' => '2026-09-07', 'end_date' => '2026-09-08']);
        Absence::factory()->for($user)->refused()->create(['start_date' => '2026-09-09', 'end_date' => '2026-09-10']);

        $this->assertSame(
            ['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'],
            app(CalendarService::class)->expectedReportDaysFor($user, '2026-09-07', '2026-09-13'),
        );
    }

    public function test_ac_6_punctuality_denominator_excludes_closed_and_approved_absence_days(): void
    {
        $user = User::factory()->active()->create();
        Holiday::factory()->create(['date' => '2026-09-09']);
        Absence::factory()->for($user)->approved()->create(['start_date' => '2026-09-10', 'end_date' => '2026-09-10']);

        $expectedDays = app(CalendarService::class)->expectedReportDaysFor($user, '2026-09-01', '2026-09-30');

        $this->assertNotContains('2026-09-09', $expectedDays);
        $this->assertNotContains('2026-09-10', $expectedDays);
        $this->assertSame(20, count($expectedDays));
    }
}
