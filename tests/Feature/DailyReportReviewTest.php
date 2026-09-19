<?php

namespace Tests\Feature;

use App\Enums\DailyReportDecisionType;
use App\Enums\DailyReportState;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Notifications\DailyReportDecisionNotification;
use App\Services\Accountability\DailyReportReviewService;
use Database\Seeders\SettingSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DailyReportReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Queue::fake();
    }

    public function test_tutor_queue_contains_only_subordinates_and_is_sorted_by_age(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $first = User::factory()->active()->withManager($tutor)->create();
        $second = User::factory()->active()->withManager($tutor)->create();
        $outsider = User::factory()->active()->create();
        $oldest = $this->submittedReport($first, '2026-08-10 15:00:00');
        $newest = $this->submittedReport($second, '2026-08-11 15:00:00');
        $this->submittedReport($outsider, '2026-08-09 15:00:00');

        $queue = $this->service()->pending($tutor);

        $this->assertSame([$oldest->getKey(), $newest->getKey()], $queue->modelKeys());
    }

    public function test_validation_is_audited_notifies_author_and_cannot_be_repeated(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $author = User::factory()->active()->withManager($tutor)->create();
        $report = $this->submittedReport($author, '2026-08-11 15:00:00');

        $validated = $this->service()->decide($report, $tutor, DailyReportDecisionType::Valider);

        $this->assertSame(DailyReportState::Valide, $validated->state);
        $this->assertDatabaseHas('daily_report_decisions', ['daily_report_id' => $report->getKey(), 'reviewer_id' => $tutor->getKey(), 'decision' => 'valider']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $report->getKey(), 'action' => 'daily_report_validated']);
        $this->assertSame(1, DatabaseNotification::query()->where('type', DailyReportDecisionNotification::class)->count());

        $this->expectException(ValidationException::class);
        $this->service()->decide($validated, $tutor, DailyReportDecisionType::Valider);
    }

    public function test_return_requires_reason_and_preserves_author_fields(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $author = User::factory()->active()->create();
        $report = $this->submittedReport($author, '2026-08-11 15:00:00');
        $original = $report->currentVersion()->firstOrFail()->achieved_result;

        try {
            $this->service()->decide($report, $direction, DailyReportDecisionType::Retourner);
            $this->fail('Le motif du retour est obligatoire.');
        } catch (ValidationException) {
            $this->assertSame(DailyReportState::Envoye, $report->fresh()->state);
        }

        $returned = $this->service()->decide($report, $direction, DailyReportDecisionType::Retourner, 'Ajoutez la preuve manquante.');

        $this->assertSame(DailyReportState::Retourne, $returned->state);
        $this->assertSame($original, $returned->currentVersion()->firstOrFail()->achieved_result);
        $this->assertStringContainsString('Ajoutez la preuve', DatabaseNotification::query()->latest('created_at')->firstOrFail()->data['message']);
    }

    public function test_out_of_scope_tutor_and_direction_cannot_edit_author_version(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $direction = User::factory()->active()->withRole('direction')->create();
        $author = User::factory()->active()->create();
        $report = $this->submittedReport($author, '2026-08-11 15:00:00');

        $this->assertFalse($tutor->can('review', $report));
        $this->assertFalse($direction->can('update', $report));
        $this->assertTrue($direction->can('review', $report));

        $this->expectException(AuthorizationException::class);
        $this->service()->comment($report, $tutor, 'Commentaire hors périmètre');
    }

    public function test_review_payload_exposes_private_download_and_signed_thumbnail_urls(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $author = User::factory()->active()->withManager($tutor)->create();
        $report = $this->submittedReport($author, '2026-08-11 15:00:00');
        $version = $report->currentVersion()->firstOrFail();
        $attachment = Attachment::factory()->create([
            'attachable_type' => $version->getMorphClass(),
            'attachable_id' => $version->getKey(),
            'uploaded_by' => $author->getKey(),
            'thumbnail_path' => 'accountability/daily-report-version/thumbnails/proof.jpg',
        ]);

        $payload = $this->service()->item($report);

        $this->assertSame(route('attachments.show', $attachment), $payload['current']['attachment_url']);
        $this->assertStringContainsString('/vignette?', $payload['current']['thumbnail_url']);
        $this->assertStringContainsString('signature=', $payload['current']['thumbnail_url']);
    }

    private function service(): DailyReportReviewService
    {
        return $this->app->make(DailyReportReviewService::class);
    }

    private function submittedReport(User $author, string $submittedAt): DailyReport
    {
        $report = DailyReport::factory()->submitted()->create([
            'author_id' => $author->getKey(),
            'report_date' => substr($submittedAt, 0, 10),
            'submitted_at' => $submittedAt,
        ]);
        DailyReportVersion::factory()->create([
            'daily_report_id' => $report->getKey(),
            'author_id' => $author->getKey(),
        ]);

        return $report;
    }
}
