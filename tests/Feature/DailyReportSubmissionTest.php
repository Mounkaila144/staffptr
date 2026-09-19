<?php

namespace Tests\Feature;

use App\Enums\DailyReportState;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Identity\User;
use App\Models\Platform\Setting;
use App\Models\Work\Task;
use App\Notifications\DailyReportSubmittedNotification;
use App\Observers\AuditableObserver;
use App\Services\Accountability\DailyReportService;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

class DailyReportSubmissionTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_form_is_prefilled_from_tasks_assigned_for_the_civil_day(): void
    {
        $actor = User::factory()->active()->create();
        Task::factory()->create([
            'assignee_id' => $actor->getKey(),
            'due_date' => '2026-08-11',
            'title' => 'Préparer la démonstration',
        ]);

        $form = $this->service()->formFor($actor, $this->at('16:00'));

        $this->assertTrue($form['expected']);
        $this->assertSame('Préparer la démonstration', $form['planned_task']);
        $this->assertNull($form['report']);
    }

    public function test_submission_is_atomic_and_records_explicit_negative_answers(): void
    {
        $actor = User::factory()->active()->create();

        $report = $this->service()->submit($this->validData(), $actor, $this->at('16:00'));

        $this->assertSame(DailyReportState::Envoye, $report->state);
        $this->assertSame('2026-08-11', $report->report_date->toDateString());
        $this->assertDatabaseCount('daily_reports', 1);
        $this->assertDatabaseHas('daily_report_versions', [
            'daily_report_id' => $report->getKey(),
            'version_number' => 1,
            'blocker_present' => false,
            'help_requested' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => DailyReport::class,
            'auditable_id' => $report->getKey(),
            'action' => 'daily_report_submitted',
        ]);
    }

    public function test_retry_is_idempotent_and_a_new_correction_keeps_the_same_report_identity(): void
    {
        $actor = User::factory()->active()->create();
        $service = $this->service();
        $data = $this->validData();

        $first = $service->submit($data, $actor, $this->at('16:00'));
        $retry = $service->submit($data, $actor, $this->at('16:01'));
        $corrected = $service->submit([
            ...$data,
            'idempotency_key' => '5176ace6-69a2-42ca-8536-cb6509efcb30',
            'achieved_result' => 'Résultat corrigé',
            'correction_reason' => 'Précision ajoutée',
        ], $actor, $this->at('16:02'));

        $this->assertTrue($first->is($retry));
        $this->assertTrue($first->is($corrected));
        $this->assertDatabaseCount('daily_reports', 1);
        $this->assertDatabaseCount('daily_report_versions', 2);
        $this->assertSame(2, DailyReportVersion::query()->max('version_number'));
    }

    public function test_a_correction_requires_a_reason_and_keeps_the_previous_version_unchanged(): void
    {
        $actor = User::factory()->active()->create();
        $service = $this->service();
        $first = $service->submit($this->validData(), $actor, $this->at('16:00'));
        $originalResult = $first->currentVersion()->firstOrFail()->achieved_result;

        try {
            $service->submit([
                ...$this->validData(),
                'idempotency_key' => 'ae864f28-4ef0-4688-ad2b-b879141e6cb6',
                'achieved_result' => 'Résultat sans motif',
            ], $actor, $this->at('16:05'));
            $this->fail('Le motif de correction doit être obligatoire.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('correction_reason', $exception->errors());
        }

        $this->assertDatabaseCount('daily_report_versions', 1);
        $this->assertSame($originalResult, $first->currentVersion()->firstOrFail()->achieved_result);
    }

    public function test_audit_failure_rolls_back_the_whole_submission(): void
    {
        $actor = User::factory()->active()->create();
        $logger = $this->mock(AuditLogger::class);
        $logger->shouldReceive('record')->andThrow(new RuntimeException('audit unavailable'));
        $this->app->forgetInstance(AuditableObserver::class);

        try {
            $this->service()->submit($this->validData(), $actor, $this->at('16:00'));
            $this->fail("L'échec d'audit attendu n'a pas été levé.");
        } catch (RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('daily_reports', 0);
        $this->assertDatabaseCount('daily_report_versions', 0);
    }

    public function test_deadline_is_inclusive_and_a_later_submission_is_marked_late(): void
    {
        $onTimeUser = User::factory()->active()->create();
        $lateUser = User::factory()->active()->create();

        $onTime = $this->service()->submit($this->validData(), $onTimeUser, $this->at('17:45:00'));
        $late = $this->service()->submit([
            ...$this->validData(),
            'idempotency_key' => 'a17deefb-4afd-4338-a21a-455d63434812',
            'lateness_explanation' => 'La connexion était instable.',
        ], $lateUser, $this->at('17:45:01'));

        $this->assertSame(DailyReportState::Envoye, $onTime->state);
        $this->assertSame(DailyReportState::EnRetard, $late->state);
        $this->assertSame('La connexion était instable.', $late->lateness_explanation);
    }

    public function test_a_correction_after_the_deadline_keeps_the_original_submission_time(): void
    {
        $actor = User::factory()->active()->create();
        $service = $this->service();
        $data = $this->validData();

        $first = $service->submit($data, $actor, $this->at('16:00'));
        $corrected = $service->submit([
            ...$data,
            'idempotency_key' => 'd6b6e0e0-2b0a-4a6d-9d1a-0d3f5a1c8e21',
            'achieved_result' => 'Résultat corrigé',
            'correction_reason' => 'Précision demandée par le tuteur',
        ], $actor, $this->at('19:00'));

        $this->assertSame(DailyReportState::Envoye, $corrected->state);
        $this->assertTrue($first->submitted_at->equalTo($corrected->submitted_at));
        $this->assertNull($corrected->lateness_explanation);
        $this->assertSame(2, DailyReportVersion::query()->max('version_number'));
    }

    public function test_a_correction_of_a_late_report_keeps_its_lateness_and_explanation(): void
    {
        $actor = User::factory()->active()->create();
        $service = $this->service();
        $data = $this->validData();

        $late = $service->submit([
            ...$data,
            'lateness_explanation' => 'La connexion était instable.',
        ], $actor, $this->at('18:30'));
        $corrected = $service->submit([
            ...$data,
            'idempotency_key' => 'f0f2c5ba-0f0f-4a11-8a2e-5a2c9d6f1b34',
            'achieved_result' => 'Résultat corrigé',
            'correction_reason' => 'Ajout du détail manquant',
            'lateness_explanation' => null,
        ], $actor, $this->at('19:30'));

        $this->assertSame(DailyReportState::EnRetard, $corrected->state);
        $this->assertTrue($late->submitted_at->equalTo($corrected->submitted_at));
        $this->assertSame('La connexion était instable.', $corrected->lateness_explanation);
    }

    public function test_deadline_setting_changes_behavior_without_redeployment(): void
    {
        $setting = Setting::query()->where('key', 'report_deadline_time')->firstOrFail();
        $setting->value = '15:00';
        $setting->saveOrFail();
        Cache::forget('settings:all');
        $actor = User::factory()->active()->create();

        $report = $this->service()->submit($this->validData(), $actor, $this->at('16:00'));

        $this->assertSame(DailyReportState::EnRetard, $report->state);
    }

    public function test_submission_notifies_the_direct_reviewer_with_a_direct_validation_link(): void
    {
        Queue::fake();
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $actor = User::factory()->active()->withRole('employe')->withManager($tutor)->create();

        $report = $this->service()->submit($this->validData(), $actor, $this->at('16:00'));

        $notification = DatabaseNotification::query()
            ->where('notifiable_id', $tutor->getKey())
            ->where('type', DailyReportSubmittedNotification::class)
            ->sole();
        $this->assertSame(route('daily-report-reviews.show', $report, false), $notification->data['link']);
        Queue::assertPushed(SendQueuedNotifications::class);
    }

    private function service(): DailyReportService
    {
        return $this->app->make(DailyReportService::class);
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-08-11 {$time}", 'Africa/Niamey');
    }

    /** @return array<string, mixed> */
    private function validData(): array
    {
        return [
            'planned_task' => 'Préparer la démonstration',
            'achieved_result' => 'Démonstration préparée',
            'evidence_link' => 'https://example.test/preuve',
            'blocker_present' => false,
            'blocker_details' => null,
            'next_action' => 'Présenter la démonstration',
            'help_requested' => false,
            'help_details' => null,
            'lateness_explanation' => null,
            'correction_reason' => null,
            'idempotency_key' => 'cb830477-e603-4e80-9aa6-5e78d312c3ce',
        ];
    }
}
