<?php

namespace Tests\Feature;

use App\Enums\BlockerState;
use App\Enums\BlockerUrgency;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Task;
use App\Notifications\BlockerNotification;
use App\Services\Accountability\BlockerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BlockerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_blocker_can_be_created_from_task_objective_and_report(): void
    {
        $actor = User::factory()->active()->withRole('employe')->create();
        $solicited = User::factory()->active()->withRole('employe')->create();
        $task = Task::factory()->create(['assignee_id' => $actor->getKey(), 'created_by' => $actor->getKey()]);
        $objective = Objective::factory()->create(['user_id' => $actor->getKey(), 'created_by' => $actor->getKey()]);
        $report = DailyReport::factory()->submitted()->create(['author_id' => $actor->getKey()]);

        foreach ([['task', $task->getKey()], ['objective', $objective->getKey()], ['daily_report', $report->getKey()]] as [$type, $id]) {
            $blocker = $this->service()->create($this->data($type, (int) $id, $solicited), $actor);
            $this->assertSame($type === 'task' ? Task::class : ($type === 'objective' ? Objective::class : DailyReport::class), $blocker->origin_type);
        }

        $this->assertDatabaseCount('blockers', 3);
        $this->assertSame(3, DatabaseNotification::query()->where('type', BlockerNotification::class)->count());
        Queue::assertPushed(SendQueuedNotifications::class, 3);
    }

    public function test_urgent_blocker_notifies_immediately_and_is_audited(): void
    {
        $actor = User::factory()->active()->withRole('employe')->create();
        $solicited = User::factory()->active()->withRole('employe')->create();
        $task = Task::factory()->create(['assignee_id' => $actor->getKey()]);

        $blocker = $this->service()->create($this->data('task', (int) $task->getKey(), $solicited), $actor);

        $this->assertSame(BlockerUrgency::Urgente, $blocker->urgency);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $blocker->getKey(), 'action' => 'blocker_created']);
        $this->assertStringContainsString('urgent', DatabaseNotification::query()->firstOrFail()->data['message']);
    }

    public function test_transition_cycle_records_delays_and_closure_requires_reason(): void
    {
        CarbonImmutable::setTestNow('2026-08-11 10:00:00 UTC');
        $actor = User::factory()->active()->withRole('employe')->create();
        $solicited = User::factory()->active()->withRole('employe')->create();
        $task = Task::factory()->create(['assignee_id' => $actor->getKey()]);
        $blocker = $this->service()->create($this->data('task', (int) $task->getKey(), $solicited), $actor);
        CarbonImmutable::setTestNow('2026-08-11 10:15:00 UTC');
        $handled = $this->service()->transition($blocker, $solicited, BlockerState::PrisEnCharge);
        CarbonImmutable::setTestNow('2026-08-11 11:00:00 UTC');
        $resolved = $this->service()->transition($handled, $solicited, BlockerState::Resolu);

        $this->assertSame(15, $resolved->acknowledgementDelayMinutes());
        $this->assertSame(60, $resolved->resolutionDelayMinutes());
        $this->assertSame(BlockerState::Resolu, $resolved->state);

        $other = $this->service()->create($this->data('task', (int) $task->getKey(), $solicited), $actor);
        $this->expectException(ValidationException::class);
        $this->service()->transition($other, $solicited, BlockerState::FermeSansSolution);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function service(): BlockerService
    {
        return $this->app->make(BlockerService::class);
    }

    /** @return array<string, mixed> */
    private function data(string $type, int $id, User $solicited): array
    {
        return ['origin_type' => $type, 'origin_id' => $id, 'problem' => 'Accès nécessaire indisponible', 'urgency' => BlockerUrgency::Urgente, 'solicited_user_id' => $solicited->getKey(), 'reported_on' => '2026-08-11', 'deadline_impact' => "Risque d'un jour", 'attempted_action' => 'Vérification des accès'];
    }
}
