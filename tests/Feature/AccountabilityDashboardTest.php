<?php

namespace Tests\Feature;

use App\Enums\AbsenceState;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Services\Accountability\AccountabilityDashboardService;
use App\Services\Platform\CalendarService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountabilityDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_daily_report_block_is_the_primary_action_for_non_direction_users(): void
    {
        $user = User::factory()->active()->withRole('employe')->create();

        $block = $this->service()->dailyReportBlock($user, $this->day('2026-08-11'));

        $this->assertNotNull($block);
        $this->assertSame('Mon rapport du jour', $block['title']);
        $this->assertSame('À préparer', $block['status']);
        $this->assertFalse($block['after_approval']);
    }

    public function test_direction_block_stays_after_the_approval_queue(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        DailyReport::factory()->submitted()->create([
            'author_id' => $direction->getKey(),
            'report_date' => '2026-08-11',
        ]);

        $block = $this->service()->dailyReportBlock($direction, $this->day('2026-08-11'));

        $this->assertNotNull($block);
        $this->assertTrue($block['after_approval']);
        $this->assertSame('Envoyé', $block['status']);
    }

    public function test_block_is_absent_on_a_closed_day_or_approved_absence(): void
    {
        $user = User::factory()->active()->create();
        $manager = User::factory()->active()->create();
        $absence = Absence::factory()->for($user)->approved($manager)->create([
            'start_date' => '2026-08-11',
            'end_date' => '2026-08-11',
        ]);

        $this->assertSame($user->getKey(), $absence->user_id);
        $this->assertSame('approuvee', $absence->state->value);
        $this->assertSame('2026-08-11', $absence->start_date->toDateString());
        $this->assertSame('2026-08-11', $absence->end_date->toDateString());
        $this->assertSame(1, Absence::query()->where('user_id', $user->getKey())->where('state', AbsenceState::Approuvee)->overlapping('2026-08-11', '2026-08-11')->count());
        $this->assertSame([], $this->app->make(CalendarService::class)->expectedReportDaysFor($user, '2026-08-11', '2026-08-11'));
        $this->assertNull($this->service()->dailyReportBlock($user, $this->day('2026-08-11')), 'approved absence');
        $this->assertNull($this->service()->dailyReportBlock($user, $this->day('2026-08-15')), 'closed day');
    }

    public function test_blocks_are_absent_for_an_account_without_the_matching_permission(): void
    {
        $admin = User::factory()->active()->withRole('super_admin')->create();

        $this->assertFalse($admin->can('rapport_quotidien.creer'));
        $this->assertFalse($admin->can('blocage.consulter'));
        $this->assertNull($this->service()->dailyReportBlock($admin, $this->day('2026-08-11')));
        $this->assertNull($this->service()->openBlockers($admin));
    }

    public function test_open_blockers_block_is_rendered_for_an_authorised_account(): void
    {
        $user = User::factory()->active()->withRole('employe')->create();

        $block = $this->service()->openBlockers($user);

        $this->assertNotNull($block);
        $this->assertSame('Blocages ouverts', $block['title']);
        $this->assertSame('Aucun blocage ouvert.', $block['empty_message']);
    }

    private function service(): AccountabilityDashboardService
    {
        return $this->app->make(AccountabilityDashboardService::class);
    }

    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'Africa/Niamey');
    }
}
