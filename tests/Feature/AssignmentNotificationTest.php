<?php

namespace Tests\Feature;

use App\Enums\AbsenceState;
use App\Enums\WorkPriority;
use App\Enums\WorkTaskStatus;
use App\Exceptions\Identity\EvolutionApiUnavailable;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Models\Work\Task;
use App\Notifications\AbsenceDecidedNotification;
use App\Notifications\TaskAssignedNotification;
use App\Services\Identity\AbsenceService;
use App\Services\Platform\WhatsAppChannel;
use App\Services\Work\TaskService;
use Database\Seeders\SettingSeeder;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IdentityTestCase;

/**
 * Décision du propriétaire du 2026-11-01 : la personne à qui l'on confie une tâche, et celle dont
 * l'absence vient d'être décidée, sont prévenues dans l'application et sur WhatsApp.
 */
class AssignmentNotificationTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_the_assignee_is_notified_when_a_task_is_entrusted_to_them(): void
    {
        Notification::fake();
        $direction = User::factory()->active()->withRole('direction')->create();
        $employee = User::factory()->active()->withRole('employe')->create();

        $task = app(TaskService::class)->create($this->taskData($employee, '2026-11-14'), $direction);

        $expected = sprintf(
            '%s vous a confié la tâche « Préparer le bilan », à terminer pour le 14/11/2026.',
            $direction->person->full_name,
        );
        Notification::assertSentTo($employee, TaskAssignedNotification::class, function (TaskAssignedNotification $notification, array $channels) use ($employee, $task, $expected): bool {
            $data = $notification->toDatabase($employee);

            return $data['message'] === $expected
                && $data['link'] === "/taches/{$task->getKey()}"
                && str_contains($notification->toWhatsApp($employee), url("/taches/{$task->getKey()}"));
        });
        Notification::assertSentToTimes($employee, TaskAssignedNotification::class, 2);
        Notification::assertNotSentTo($direction, TaskAssignedNotification::class);
    }

    public function test_no_notification_when_one_entrusts_a_task_to_oneself(): void
    {
        Notification::fake();
        $employee = User::factory()->active()->withRole('employe')->create();

        app(TaskService::class)->create($this->taskData($employee, '2026-11-14'), $employee);

        Notification::assertNothingSent();
    }

    public function test_the_task_notification_reaches_the_bell_and_the_notifications_page(): void
    {
        Queue::fake();
        $direction = User::factory()->active()->withRole('direction')->create();
        $employee = User::factory()->active()->withRole('employe')->create();

        $this->actingAs($direction)
            ->post('/taches', $this->taskData($employee, '2026-11-14'))
            ->assertRedirect();
        $task = Task::query()->where('title', 'Préparer le bilan')->sole();

        // WhatsApp passe par la file : l'action n'en dépend pas.
        Queue::assertPushed(SendQueuedNotifications::class, static fn (SendQueuedNotifications $job): bool => $job->channels === [WhatsAppChannel::class]
            && $job->notification instanceof TaskAssignedNotification);
        $this->assertSame(1, $employee->unreadNotifications()->count());

        session()->flush();
        auth()->forgetGuards();
        $this->actingAs($employee)->get('/notifications')->assertOk()->assertInertia(static fn (Assert $page): Assert => $page
            ->component('Platform/Notifications/Index')
            ->where('notificationItems.data.0.link', "/taches/{$task->getKey()}")
            ->where('notificationItems.data.0.message', fn (string $message): bool => str_contains($message, '« Préparer le bilan »')
                && str_contains($message, '14/11/2026')
                && ! str_contains($message, 'undefined')));
    }

    public function test_a_whatsapp_outage_never_cancels_the_assignment_and_stays_visible_for_retries(): void
    {
        Queue::fake();
        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.key' => 'test-key',
            'services.evolution.instance' => 'ptr-test',
        ]);
        Http::fake([
            'https://evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
            'https://evolution.test/message/sendText/*' => Http::response([], 503),
        ]);
        $direction = User::factory()->active()->withRole('direction')->create();
        $employee = User::factory()->active()->withRole('employe')->create();

        $task = app(TaskService::class)->create($this->taskData($employee, '2026-11-14'), $direction);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $employee->getKey(), 'type' => TaskAssignedNotification::class]);

        // Quand le worker exécute le message en file, l'échec remonte : la file le reprend.
        $this->expectException(EvolutionApiUnavailable::class);
        $employee->notifyNow(TaskAssignedNotification::forWhatsApp($task, $direction));
    }

    public function test_the_requester_is_notified_when_the_absence_is_approved(): void
    {
        Notification::fake();
        $manager = User::factory()->active()->create();
        $employee = User::factory()->active()->withManager($manager)->create();
        $absence = Absence::factory()->for($employee)->requested()->create(['start_date' => '2026-11-10', 'end_date' => '2026-11-12']);

        app(AbsenceService::class)->approve($absence, $manager);

        Notification::assertSentTo($employee, AbsenceDecidedNotification::class, function (AbsenceDecidedNotification $notification) use ($employee, $absence): bool {
            $data = $notification->toDatabase($employee);

            return $notification->decision === AbsenceState::Approuvee
                && $data['message'] === "Votre demande d'absence du 10/11/2026 au 12/11/2026 a été approuvée."
                && $data['link'] === "/absences/{$absence->getKey()}";
        });
        Notification::assertNotSentTo($manager, AbsenceDecidedNotification::class);
    }

    public function test_the_requester_reads_the_refusal_reason(): void
    {
        Notification::fake();
        $manager = User::factory()->active()->create();
        $employee = User::factory()->active()->withManager($manager)->create();
        $absence = Absence::factory()->for($employee)->requested()->create(['start_date' => '2026-11-10', 'end_date' => '2026-11-10']);

        app(AbsenceService::class)->refuse($absence, 'Période de forte activité', $manager);

        Notification::assertSentTo($employee, AbsenceDecidedNotification::class, static fn (AbsenceDecidedNotification $notification): bool => $notification->toDatabase($employee)['message']
            === "Votre demande d'absence du 10/11/2026 a été refusée. Motif : Période de forte activité");
    }

    public function test_no_decision_notification_when_the_absence_is_cancelled_by_its_requester(): void
    {
        Notification::fake();
        $employee = User::factory()->active()->withManager(User::factory()->active()->create())->create();
        $absence = Absence::factory()->for($employee)->requested()->create();

        app(AbsenceService::class)->cancel($absence, $employee);

        Notification::assertNothingSent();
    }

    public function test_both_types_are_whitelisted_for_whatsapp(): void
    {
        $this->assertTrue(WhatsAppChannel::delivers(TaskAssignedNotification::class));
        $this->assertTrue(WhatsAppChannel::delivers(AbsenceDecidedNotification::class));
    }

    /** @return array<string, mixed> */
    private function taskData(User $assignee, string $dueDate): array
    {
        return [
            'assignee_id' => $assignee->getKey(),
            'title' => 'Préparer le bilan',
            'due_date' => $dueDate,
            'priority' => WorkPriority::Normale->value,
            'status' => WorkTaskStatus::AFaire->value,
        ];
    }
}
