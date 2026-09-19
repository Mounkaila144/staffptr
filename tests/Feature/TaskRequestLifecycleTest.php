<?php

namespace Tests\Feature;

use App\Enums\TaskRequestState;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Notifications\GenericLinkedNotification;
use App\Services\Accountability\TaskRequestService;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

class TaskRequestLifecycleTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_request_keeps_report_link_notifies_manager_and_is_audited(): void
    {
        $manager = User::factory()->active()->withRole('tuteur')->create();
        $author = User::factory()->active()->intern()->withRole('stagiaire')->withManager($manager)->create();
        $report = DailyReport::factory()->submitted()->create(['author_id' => $author->getKey()]);

        $request = $this->service()->create($report, $author, 'Je suis disponible pour une nouvelle priorité.', false);

        $this->assertSame(TaskRequestState::Ouvert, $request->state);
        $this->assertSame($report->getKey(), $request->daily_report_id);
        $this->assertSame($manager->getKey(), $request->responsible_id);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->getKey(), 'action' => 'task_request_created']);
        Queue::assertPushed(
            SendQueuedNotifications::class,
            static fn ($job): bool => $job->notification instanceof GenericLinkedNotification,
        );
        $this->assertSame([$request->getKey()], $this->service()->pendingNonUrgentInternRequestsFor($manager)->modelKeys());
    }

    public function test_responsible_can_mark_request_as_processed_once(): void
    {
        $manager = User::factory()->active()->withRole('tuteur')->create();
        $author = User::factory()->active()->withManager($manager)->create();
        $report = DailyReport::factory()->submitted()->create(['author_id' => $author->getKey()]);
        $request = $this->service()->create($report, $author, 'Nouvelle tâche', true);

        $processed = $this->service()->process($request, $manager);

        $this->assertSame(TaskRequestState::Traite, $processed->state);
        $this->assertSame($manager->getKey(), $processed->treated_by);
        $this->assertNotNull($processed->treated_at);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->getKey(), 'action' => 'task_request_processed']);
    }

    private function service(): TaskRequestService
    {
        return $this->app->make(TaskRequestService::class);
    }
}
