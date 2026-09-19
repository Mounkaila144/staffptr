<?php

namespace Tests\Feature;

use App\Enums\DailyReportNotificationType;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Models\Platform\Setting;
use App\Notifications\DailyReportReminderNotification;
use App\Services\Accountability\DailyReportReminderService;
use App\Services\Platform\SettingsService;
use App\Services\Platform\WhatsAppChannel;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

class DailyReportReminderTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Queue::fake();
    }

    public function test_reminder_is_deduplicated_and_whatsapp_is_queued_after_database(): void
    {
        $user = User::factory()->active()->withRole('employe')->create();
        $service = $this->service();
        $now = $this->at('2026-08-11 16:45:00');

        $this->assertSame(1, $service->dispatchDue($now));
        $this->assertSame(0, $service->dispatchDue($now->addMinute()));
        $this->assertDatabaseHas('notifications', [
            'id' => DailyReportReminderNotification::stableId(
                (int) $user->getKey(),
                '2026-08-11',
                DailyReportNotificationType::Rappel,
            ),
        ]);
        $this->assertSame(1, DatabaseNotification::query()->where('type', DailyReportReminderNotification::class)->count());
        Queue::assertPushed(
            SendQueuedNotifications::class,
            static fn (SendQueuedNotifications $job): bool => $job->channels === [WhatsAppChannel::class],
        );
    }

    public function test_sent_report_approved_absence_and_closed_day_receive_nothing(): void
    {
        $submitted = User::factory()->active()->withRole('employe')->create();
        $absent = User::factory()->active()->withRole('employe')->create();
        $manager = User::factory()->active()->create();
        DailyReport::factory()->submitted()->create([
            'author_id' => $submitted->getKey(),
            'report_date' => '2026-08-11',
        ]);
        Absence::factory()->for($absent)->approved($manager)->create([
            'start_date' => '2026-08-11',
            'end_date' => '2026-08-11',
        ]);

        $this->assertSame(0, $this->service()->dispatchDue($this->at('2026-08-11 16:45:00')));
        $this->assertSame(0, $this->service()->dispatchDue($this->at('2026-08-15 16:45:00')));
        $this->assertDatabaseCount('notifications', 0);
        Queue::assertNothingPushed();
    }

    public function test_late_notification_is_factual_and_distinct_from_the_reminder(): void
    {
        $user = User::factory()->active()->withRole('employe')->create();

        $this->assertSame(1, $this->service()->dispatchDue($this->at('2026-08-11 17:46:00')));

        $notification = DatabaseNotification::query()->firstOrFail();
        $this->assertSame('retard', $notification->data['notification_type']);
        $this->assertStringContainsString('Vous pouvez encore', $notification->data['message']);
        $this->assertSame(
            DailyReportReminderNotification::stableId(
                (int) $user->getKey(),
                '2026-08-11',
                DailyReportNotificationType::Retard,
            ),
            $notification->getKey(),
        );
    }

    public function test_changed_deadline_and_reminder_delay_are_read_without_redeployment(): void
    {
        $user = User::factory()->active()->withRole('employe')->create();
        $this->setSetting('report_deadline_time', '18:00');
        $this->setSetting('report_reminder_minutes', 30);

        $this->assertSame(0, $this->service()->dispatchDue($this->at('2026-08-11 17:29:00')));
        $this->assertSame(1, $this->service()->dispatchDue($this->at('2026-08-11 17:30:00')));
        $this->assertDatabaseHas('notifications', [
            'id' => DailyReportReminderNotification::stableId(
                (int) $user->getKey(),
                '2026-08-11',
                DailyReportNotificationType::Rappel,
            ),
        ]);
    }

    public function test_command_is_registered_every_minute_in_niamey_time(): void
    {
        // Ce test ne crée aucun compte : sans amorçage explicite des rôles, la permission
        // `rapport_quotidien.creer` n'existe pas et la commande échoue. Il ne passait jusqu'ici
        // que lorsqu'une autre classe l'avait créée avant lui dans le même processus.
        $this->seed(RolePermissionSeeder::class);

        $schedule = (string) file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString('ptr:send-daily-report-reminders', $schedule);
        $this->assertStringContainsString('everyMinute()', $schedule);
        $this->assertStringContainsString("timezone('Africa/Niamey')", $schedule);
        $this->artisan('ptr:send-daily-report-reminders')->assertSuccessful();
    }

    private function service(): DailyReportReminderService
    {
        return $this->app->make(DailyReportReminderService::class);
    }

    private function at(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, 'Africa/Niamey');
    }

    private function setSetting(string $key, mixed $value): void
    {
        $setting = Setting::query()->where('key', $key)->firstOrFail();
        $setting->value = $value;
        $setting->saveOrFail();
        Cache::forget(SettingsService::CACHE_KEY);
    }
}
