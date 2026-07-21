<?php

namespace Tests\Feature;

use App\Exceptions\Identity\EvolutionApiUnavailable;
use App\Models\Identity\User;
use App\Notifications\GenericLinkedNotification;
use App\Services\Platform\EvolutionApiClient;
use App\Services\Platform\WhatsAppChannel;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IdentityTestCase;

class NotificationDeliveryTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.key' => 'evolution-test-key',
            'services.evolution.instance' => 'ptr-test',
        ]);
    }

    public function test_ac_1_notifications_schema_is_native_indexed_mutable_without_delete_privilege(): void
    {
        $this->assertTrue(Schema::hasColumns('notifications', [
            'id',
            'type',
            'notifiable_type',
            'notifiable_id',
            'data',
            'read_at',
            'created_at',
            'updated_at',
        ]));

        $indexes = collect(Schema::getIndexes('notifications'))->pluck('name');
        $this->assertContains('notifications_unread_count_index', $indexes);

        $migration = (string) file_get_contents(database_path('migrations/2026_07_21_070509_create_notifications_table.php'));
        $runbook = (string) file_get_contents(base_path('docs/ops/database-users.md'));

        $this->assertStringContainsString('GRANT UPDATE ON', $migration);
        $this->assertStringNotContainsString('GRANT DELETE ON', $migration);
        $this->assertStringContainsString('`ptrstaff_prod`.`notifications`', $runbook);
        $this->assertStringContainsString('`ptrstaff_staging`.`notifications`', $runbook);
    }

    public function test_ac_2_database_and_whatsapp_are_distinct_queued_jobs_with_retries(): void
    {
        Queue::fake();
        $user = User::factory()->active()->create();
        $notification = new GenericLinkedNotification('Une action vous attend.', '/');

        $user->notify($notification);

        $this->assertSame(['database', WhatsAppChannel::class], $notification->via($user));
        $this->assertSame(3, $notification->tries);
        $this->assertSame([60, 300, 900], $notification->backoff());
        Queue::assertPushed(
            SendQueuedNotifications::class,
            static fn (SendQueuedNotifications $job): bool => $job->channels === ['database'],
        );
        Queue::assertPushed(
            SendQueuedNotifications::class,
            static fn (SendQueuedNotifications $job): bool => $job->channels === [WhatsAppChannel::class],
        );
    }

    public function test_ac_2_notification_uses_the_native_laravel_dispatcher(): void
    {
        Notification::fake();
        $user = User::factory()->active()->create();

        $user->notify(new GenericLinkedNotification('Notification Laravel.', '/'));

        Notification::assertSentTo(
            $user,
            GenericLinkedNotification::class,
            static fn (GenericLinkedNotification $notification): bool => $notification->link === '/',
        );
    }

    public function test_ac_2_database_remains_source_of_truth_when_whatsapp_fails(): void
    {
        config()->set('queue.default', 'sync');
        $user = User::factory()->active()->create();
        Http::fake([
            'https://evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
            'https://evolution.test/message/sendText/*' => Http::response([], 503),
        ]);

        try {
            $user->notify(new GenericLinkedNotification('Une action vous attend.', '/'));
            $this->fail("L'échec WhatsApp devait rester visible dans la file.");
        } catch (EvolutionApiUnavailable) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_type' => User::class,
                'notifiable_id' => $user->getKey(),
            ]);
        }
    }

    public function test_ac_3_database_and_whatsapp_payloads_carry_the_direct_internal_link(): void
    {
        config()->set('queue.default', 'sync');
        $user = User::factory()->active()->create();
        Http::fake([
            'https://evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
            'https://evolution.test/message/sendText/*' => Http::response(['key' => ['id' => 'notification-id']], 201),
        ]);

        $user->notify(new GenericLinkedNotification('Consultez cet élément.', '/'));

        $stored = $user->notifications()->sole();
        $this->assertSame('Consultez cet élément.', $stored->data['message']);
        $this->assertSame('/', $stored->data['link']);
        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/message/sendText/')
            && $request['number'] === substr($user->phone, 1)
            && str_contains((string) $request['text'], 'Consultez cet élément.')
            && str_contains((string) $request['text'], url('/')));
    }

    public function test_ac_6_only_database_and_whatsapp_are_selected_and_evolution_client_is_generic(): void
    {
        $user = User::factory()->active()->create();
        $notification = new GenericLinkedNotification('Notification générique.', '/');
        Http::fake([
            'https://evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
            'https://evolution.test/message/sendText/*' => Http::response(['key' => ['id' => 'generic-id']], 201),
        ]);

        $this->assertSame(['database', WhatsAppChannel::class], $notification->via($user));
        $this->assertNotContains('mail', $notification->via($user));
        $this->assertNotContains('sms', $notification->via($user));

        app(EvolutionApiClient::class)->sendText($user->phone, 'Texte réutilisable.');

        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/message/sendText/')
            && $request['number'] === substr($user->phone, 1)
            && $request['text'] === 'Texte réutilisable.');
    }

    public function test_ac_6_generic_notification_rejects_external_links(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GenericLinkedNotification('Lien externe.', 'https://example.com/action');
    }
}
