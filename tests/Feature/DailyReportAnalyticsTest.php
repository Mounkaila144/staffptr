<?php

namespace Tests\Feature;

use App\Enums\DailyReportState;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Services\Accountability\DailyReportAnalyticsService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_daily_missing_list_excludes_absence_and_is_alphabetical(): void
    {
        $tutor = $this->user('Tuteur', 'tuteur');
        $zoe = $this->user('Zoé', 'employe', $tutor);
        $alice = $this->user('Alice', 'employe', $tutor);
        $absent = $this->user('Bruno', 'employe', $tutor);
        $this->report($tutor, DailyReportState::Envoye, '2026-08-11 16:00:00');
        Absence::factory()->for($absent)->approved($tutor)->create(['start_date' => '2026-08-11', 'end_date' => '2026-08-11']);

        $this->assertDatabaseCount('daily_reports', 1);
        $this->assertSame(1, DailyReport::query()->visibleTo($tutor)->count());
        $view = $this->service()->view($tutor, 'quotidienne', $this->day('2026-08-11'));

        $this->assertCount(1, $view['reports']);
        $this->assertSame(['Alice', 'Zoé'], array_column($view['missing'], 'name'));
        $this->assertSame(3, $view['punctuality']['expected']);
        $this->assertSame(1, $view['punctuality']['on_time']);
        $this->assertSame(33.3, $view['punctuality']['percentage']);
        $this->assertNotContains($absent->getKey(), array_column($view['missing'], 'user_id'));
    }

    public function test_late_report_is_received_but_not_counted_as_punctual(): void
    {
        $direction = $this->user('Direction', 'direction');
        $employee = $this->user('Employé', 'employe');
        $this->report($direction, DailyReportState::Envoye, '2026-08-11 17:45:00');
        $this->report($employee, DailyReportState::EnRetard, '2026-08-11 17:45:01');

        $this->assertTrue($direction->hasRole('direction'));
        $this->assertDatabaseCount('daily_reports', 2);
        $this->assertSame(2, DailyReport::query()->visibleTo($direction)->count());
        $view = $this->service()->view($direction, 'quotidienne', $this->day('2026-08-11'));

        $this->assertCount(2, $view['reports']);
        $this->assertSame([], $view['missing']);
        $this->assertSame(2, $view['punctuality']['expected']);
        $this->assertSame(1, $view['punctuality']['on_time']);
        $this->assertSame(50.0, $view['punctuality']['percentage']);
    }

    public function test_punctuality_follows_the_submission_time_not_the_review_state(): void
    {
        $direction = $this->user('Direction', 'direction');
        $returned = $this->user('Amina', 'employe');
        $validated = $this->user('Boubacar', 'employe');
        $this->report($direction, DailyReportState::Envoye, '2026-08-11 16:00:00');
        $this->report($returned, DailyReportState::Retourne, '2026-08-11 16:00:00');
        $this->report($validated, DailyReportState::Valide, '2026-08-11 16:00:00');

        $view = $this->service()->view($direction, 'quotidienne', $this->day('2026-08-11'));

        $this->assertSame(3, $view['punctuality']['expected']);
        $this->assertSame(3, $view['punctuality']['on_time']);
        $this->assertSame(100.0, $view['punctuality']['percentage']);
    }

    public function test_weekly_and_monthly_periods_are_civil_periods(): void
    {
        $employee = $this->user('Employé', 'employe');

        $week = $this->service()->view($employee, 'hebdomadaire', $this->day('2026-08-11'));
        $month = $this->service()->view($employee, 'mensuelle', $this->day('2026-08-11'));

        $this->assertSame('2026-08-10', $week['from']);
        $this->assertSame('2026-08-16', $week['to']);
        $this->assertSame('2026-08-01', $month['from']);
        $this->assertSame('2026-08-31', $month['to']);
    }

    private function service(): DailyReportAnalyticsService
    {
        return $this->app->make(DailyReportAnalyticsService::class);
    }

    private function user(string $name, string $role, ?User $manager = null): User
    {
        $user = User::factory()->active()->withRole($role)->create(['manager_id' => $manager?->getKey()]);
        $user->person->update(['full_name' => $name]);

        return $user;
    }

    private function report(User $author, DailyReportState $state, string $submittedAt): DailyReport
    {
        $report = DailyReport::factory()->create([
            'author_id' => $author->getKey(),
            'report_date' => substr($submittedAt, 0, 10),
            'state' => $state,
            'submitted_at' => CarbonImmutable::parse($submittedAt, 'Africa/Niamey')->utc(),
        ]);
        DailyReportVersion::factory()->create(['daily_report_id' => $report->getKey(), 'author_id' => $author->getKey()]);

        return $report;
    }

    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'Africa/Niamey');
    }
}
