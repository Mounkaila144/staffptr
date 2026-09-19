<?php

namespace Tests\Feature\Http;

use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Work\Task;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountabilityHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-11 16:00:00 Africa/Niamey');
        $this->seed(SettingSeeder::class);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_employee_opens_prefilled_mobile_form_and_submits_complete_report(): void
    {
        $employee = User::factory()->active()->withRole('employe')->create();
        Task::factory()->create(['assignee_id' => $employee->getKey(), 'title' => 'Préparer le point quotidien', 'due_date' => '2026-08-11']);

        $this->actingAs($employee)->get(route('daily-reports.today'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Accountability/DailyReports/Edit')->where('daily.planned_task', 'Préparer le point quotidien')->where('daily.expected', true));

        $this->actingAs($employee)->post(route('daily-reports.store'), $this->reportData())->assertRedirect(route('daily-reports.today'));
        $this->assertDatabaseCount('daily_reports', 1);
        $this->assertDatabaseCount('daily_report_versions', 1);
    }

    public function test_report_rejects_missing_proof_and_partial_fields(): void
    {
        $employee = User::factory()->active()->withRole('employe')->create();

        $this->actingAs($employee)->from(route('daily-reports.today'))->post(route('daily-reports.store'), [
            'idempotency_key' => '27992270-8aaa-4145-bc80-cb055881945a', 'blocker_present' => false, 'help_requested' => false,
        ])->assertRedirect(route('daily-reports.today'))->assertSessionHasErrors(['planned_task', 'achieved_result', 'evidence_link', 'next_action']);

        $this->assertDatabaseCount('daily_reports', 0);
    }

    public function test_tutor_can_review_only_direct_subordinate_report(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $subordinate = User::factory()->active()->withManager($tutor)->create();
        $outsider = User::factory()->active()->withRole('tuteur')->create();
        $report = $this->report($subordinate);

        $this->actingAs($tutor)->get(route('daily-report-reviews.show', $report))->assertOk();
        $this->flushSession();
        $this->actingAs($outsider)->get(route('daily-report-reviews.show', $report))->assertForbidden();
    }

    public function test_task_request_and_blocker_endpoints_enforce_origin_ownership(): void
    {
        $manager = User::factory()->active()->withRole('tuteur')->create();
        $employee = User::factory()->active()->withRole('employe')->withManager($manager)->create();
        $other = User::factory()->active()->withRole('employe')->create();
        $report = $this->report($employee);
        $task = Task::factory()->create(['assignee_id' => $employee->getKey()]);

        $this->actingAs($employee)->post(route('task-requests.store', $report), ['description' => 'Nouvelle priorité', 'is_urgent' => false])->assertRedirect();
        $this->actingAs($employee)->post(route('blockers.store'), $this->blockerData($task, $manager))->assertRedirect();
        $this->flushSession();
        $this->actingAs($other)->post(route('blockers.store'), $this->blockerData($task, $manager))->assertForbidden();
        $this->assertDatabaseCount('task_requests', 1);
        $this->assertDatabaseCount('blockers', 1);
    }

    public function test_private_report_proof_is_visible_only_inside_report_scope(): void
    {
        Storage::fake('private');
        $author = User::factory()->active()->withRole('employe')->create();
        $outsider = User::factory()->active()->withRole('employe')->create();
        $report = $this->report($author);
        $version = $report->currentVersion()->firstOrFail();
        $attachment = Attachment::factory()->create(['attachable_type' => $version->getMorphClass(), 'attachable_id' => $version->getKey(), 'uploaded_by' => $author->getKey()]);
        Storage::disk('private')->put($attachment->path, 'preuve');

        $this->actingAs($author)->get(route('attachments.show', $attachment))->assertOk();
        $this->flushSession();
        $this->actingAs($outsider)->get(route('attachments.show', $attachment))->assertForbidden();
    }

    private function report(User $author): DailyReport
    {
        $report = DailyReport::factory()->submitted()->create(['author_id' => $author->getKey(), 'report_date' => '2026-08-11']);
        DailyReportVersion::factory()->create(['daily_report_id' => $report->getKey(), 'author_id' => $author->getKey()]);

        return $report;
    }

    /** @return array<string, mixed> */
    private function reportData(): array
    {
        return ['planned_task' => 'Préparer le point', 'achieved_result' => 'Point préparé', 'evidence_link' => 'https://example.test/preuve', 'blocker_present' => false, 'next_action' => 'Présenter le point', 'help_requested' => false, 'idempotency_key' => '63aad9e8-13be-4724-a752-c90a5a51d4a4'];
    }

    /** @return array<string, mixed> */
    private function blockerData(Task $task, User $solicited): array
    {
        return ['origin_type' => 'task', 'origin_id' => $task->getKey(), 'problem' => 'Accès manquant', 'urgency' => 'urgente', 'solicited_user_id' => $solicited->getKey(), 'reported_on' => '2026-08-11', 'deadline_impact' => "Risque d'un jour", 'attempted_action' => 'Vérification effectuée'];
    }
}
