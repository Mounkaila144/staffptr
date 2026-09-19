<?php

namespace Tests\Feature;

use App\Enums\ExpenseState;
use App\Exceptions\Identity\EvolutionApiUnavailable;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Notifications\ExpenseApprovalReminderNotification;
use App\Services\Finance\ExpenseApprovalReminderService;
use App\Services\Platform\WhatsAppChannel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

class ExpenseApprovalReminderTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    private User $firstDirection;

    private User $secondDirection;

    private User $requester;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-08-10 08:00:00 Africa/Niamey');
        $this->firstDirection = User::factory()->active()->withRole('direction')->create();
        $this->secondDirection = User::factory()->active()->withRole('direction')->create();
        $this->requester = User::factory()->active()->withRole('employe')->create();
        $this->category = ExpenseCategory::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_ac_4_j_plus_1_and_j_plus_2_target_only_missing_approvers(): void
    {
        Queue::fake();
        $jPlusOne = $this->expenseCreatedDaysAgo(1);
        $jPlusTwo = $this->expenseCreatedDaysAgo(2);
        ExpenseApproval::factory()->approved()->create([
            'expense_id' => $jPlusOne->getKey(),
            'approver_id' => $this->firstDirection->getKey(),
        ]);

        $sent = app(ExpenseApprovalReminderService::class)->dispatchDue();

        $this->assertSame(3, $sent);
        $this->assertSame(3, DatabaseNotification::query()
            ->where('type', ExpenseApprovalReminderNotification::class)
            ->count());
        $this->assertDatabaseMissing('notifications', [
            'id' => ExpenseApprovalReminderNotification::stableId(
                (int) $jPlusOne->getKey(),
                (int) $this->firstDirection->getKey(),
                1,
            ),
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => ExpenseApprovalReminderNotification::stableId(
                (int) $jPlusOne->getKey(),
                (int) $this->secondDirection->getKey(),
                1,
            ),
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => ExpenseApprovalReminderNotification::stableId(
                (int) $jPlusTwo->getKey(),
                (int) $this->firstDirection->getKey(),
                2,
            ),
        ]);
        Queue::assertPushed(SendQueuedNotifications::class, 3);
        Queue::assertPushed(
            SendQueuedNotifications::class,
            static fn (SendQueuedNotifications $job): bool => $job->channels === [WhatsAppChannel::class],
        );
    }

    public function test_ac_4_task_is_idempotent_and_ignores_before_j_plus_1_and_after_j_plus_2(): void
    {
        Queue::fake();
        $this->expenseCreatedDaysAgo(1);
        $this->expenseCreatedDaysAgo(0);
        $this->expenseCreatedDaysAgo(3);
        $service = app(ExpenseApprovalReminderService::class);

        $this->assertSame(2, $service->dispatchDue());
        $this->assertSame(0, $service->dispatchDue());
        $this->assertSame(2, DatabaseNotification::query()
            ->where('type', ExpenseApprovalReminderNotification::class)
            ->count());
        Queue::assertPushed(SendQueuedNotifications::class, 2);
    }

    public function test_ac_5_no_reminder_after_approval_refusal_cancellation_or_recipient_decision(): void
    {
        Queue::fake();
        $approved = $this->expenseCreatedDaysAgo(1, ExpenseState::Approuvee);
        $refused = $this->expenseCreatedDaysAgo(1, ExpenseState::Refusee);
        $cancelled = $this->expenseCreatedDaysAgo(1, ExpenseState::Annulee);
        $partiallyHandled = $this->expenseCreatedDaysAgo(1);
        ExpenseApproval::factory()->approved()->create([
            'expense_id' => $partiallyHandled->getKey(),
            'approver_id' => $this->firstDirection->getKey(),
        ]);

        $this->assertSame(1, app(ExpenseApprovalReminderService::class)->dispatchDue());
        $this->assertDatabaseHas('notifications', [
            'id' => ExpenseApprovalReminderNotification::stableId(
                (int) $partiallyHandled->getKey(),
                (int) $this->secondDirection->getKey(),
                1,
            ),
        ]);
        foreach ([$approved, $refused, $cancelled] as $expense) {
            $this->assertDatabaseMissing('notifications', ['data->expense_id' => $expense->getKey()]);
        }
    }

    public function test_database_notification_remains_when_queued_whatsapp_fails_and_no_other_external_channel_is_used(): void
    {
        config([
            'queue.default' => 'sync',
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.key' => 'test-key',
            'services.evolution.instance' => 'ptr-test',
        ]);
        Http::fake([
            'https://evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
            'https://evolution.test/message/sendText/*' => Http::response([], 503),
        ]);
        $expense = $this->expenseCreatedDaysAgo(1);

        try {
            app(ExpenseApprovalReminderService::class)->dispatchDue();
            $this->fail("L'échec WhatsApp doit rester visible pour les reprises de la file.");
        } catch (EvolutionApiUnavailable) {
            $this->assertDatabaseHas('notifications', [
                'id' => ExpenseApprovalReminderNotification::stableId(
                    (int) $expense->getKey(),
                    (int) $this->firstDirection->getKey(),
                    1,
                ),
            ]);
        }

        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), 'evolution.test'));
        $notification = ExpenseApprovalReminderNotification::forWhatsApp(
            $expense,
            1,
            (int) $this->firstDirection->getKey(),
        );
        $this->assertSame([WhatsAppChannel::class], $notification->via($this->firstDirection));
        $this->assertNotContains('mail', $notification->via($this->firstDirection));
        $this->assertNotContains('sms', $notification->via($this->firstDirection));
    }

    public function test_schedule_uses_niamey_civil_time_and_explicit_daily_command(): void
    {
        Queue::fake();
        $schedule = (string) file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString("dailyAt('08:00')", $schedule);
        $this->assertStringContainsString("timezone('Africa/Niamey')", $schedule);
        $this->artisan('ptr:send-expense-approval-reminders')->assertSuccessful();
    }

    private function expenseCreatedDaysAgo(int $days, ExpenseState $state = ExpenseState::Demandee): Expense
    {
        $createdAt = CarbonImmutable::now('Africa/Niamey')->subDays($days)->setTime(12, 0)->utc();

        return Expense::factory()
            ->for($this->requester, 'requester')
            ->for($this->category, 'category')
            ->create([
                'state' => $state->value,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
    }
}
