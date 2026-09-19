<?php

namespace Tests\Feature;

use App\Enums\BlockerUrgency;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\Internship;
use App\Models\Accountability\SupportRequestBatch;
use App\Models\Accountability\TutorSupportSlot;
use App\Models\Identity\User;
use App\Models\Work\Task;
use App\Notifications\GroupedSupportRequestNotification;
use App\Services\Accountability\BlockerService;
use App\Services\Accountability\SupportRequestGroupingService;
use App\Services\Platform\Invariants\QueuedNotificationFailureInvariant;
use App\Services\Platform\WhatsAppChannel;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Task 11 — notifications `database` d'abord, WhatsApp en file (AC 35 à 39, 44).
 */
class EpicSevenNotificationContractTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Queue::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-10 08:00:00', 'Africa/Niamey'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * `database` est la source de vérité : la notification existe en base dès l'émission, sans
     * dépendre de la file.
     */
    public function test_database_is_the_source_of_truth_and_whatsapp_leaves_the_http_cycle(): void
    {
        $batch = $this->deliveredBatch();

        $stored = DatabaseNotification::query()
            ->where('type', GroupedSupportRequestNotification::class)
            ->get();

        $this->assertCount(1, $stored, 'La notification database doit exister sans attendre la file.');

        // L'envoi WhatsApp, lui, est mis en file : il ne s'exécute jamais dans le cycle HTTP.
        Queue::assertPushed(SendQueuedNotifications::class);

        $this->assertNotNull($batch->delivered_at);
    }

    /**
     * Aucun canal non autorisé : seuls `database` et le canal WhatsApp interne sont employés.
     */
    public function test_only_database_and_whatsapp_channels_are_used(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $batch = SupportRequestBatch::factory()->create(['tutor_id' => $tutor->getKey()]);

        $database = GroupedSupportRequestNotification::forDatabase($batch, [1, 2]);
        $whatsApp = GroupedSupportRequestNotification::forWhatsApp($batch, [1, 2]);

        $this->assertSame(['database'], $database->via($tutor));
        $this->assertSame([WhatsAppChannel::class], $whatsApp->via($tutor));

        foreach (['mail', 'sms', 'nexmo', 'vonage', 'slack', 'broadcast'] as $forbidden) {
            $this->assertNotContains($forbidden, $database->via($tutor));
            $this->assertNotContains($forbidden, $whatsApp->via($tutor));
        }
    }

    /**
     * La notification porte une URL directe vers les demandes, et chaque demande y reste
     * individuellement adressable (AC 38).
     */
    public function test_the_notification_links_to_the_requests_and_keeps_them_addressable(): void
    {
        $this->deliveredBatch(requestCount: 3);

        $payload = DatabaseNotification::query()
            ->where('type', GroupedSupportRequestNotification::class)
            ->firstOrFail()
            ->data;

        $this->assertSame('/blocages', $payload['link']);
        $this->assertSame(3, $payload['request_count']);
        $this->assertCount(3, $payload['request_ids']);

        // Chaque identifiant correspond bien à un blocage réel adressé à ce tuteur.
        foreach ($payload['request_ids'] as $requestId) {
            $this->assertTrue(Blocker::query()->whereKey($requestId)->exists());
        }
    }

    /**
     * Le message WhatsApp ne contient que du texte et un lien de l'application : aucune
     * ressource tierce, aucun contenu distant.
     */
    public function test_the_whatsapp_message_carries_no_third_party_resource(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $batch = SupportRequestBatch::factory()->create(['tutor_id' => $tutor->getKey()]);

        $message = GroupedSupportRequestNotification::forWhatsApp($batch, [1])->toWhatsApp($tutor);

        $this->assertStringContainsString('PTR Staff', $message);
        $this->assertStringContainsString(config('app.url'), $message);

        foreach (['cdn.', 'googleapis', '<img', '<script'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $message);
        }
    }

    /**
     * Les échecs de file sont exposés à la supervision par `ptr:check-invariants`.
     */
    public function test_queue_failures_are_exposed_to_supervision(): void
    {
        $invariant = app(QueuedNotificationFailureInvariant::class);

        $this->assertTrue($invariant->check()->passed);
        $this->assertSame('aucun envoi en échec', $invariant->check()->observed);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Evolution API injoignable.',
            'failed_at' => now(),
        ]);

        $result = $invariant->check();

        $this->assertFalse($result->passed);
        $this->assertSame('1 envoi en échec de file', $result->observed);
    }

    /**
     * Une notification destinée à un autre compte n'est jamais délivrée.
     */
    public function test_a_notification_is_never_delivered_to_the_wrong_account(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $someoneElse = User::factory()->active()->withRole('employe')->create();
        $batch = SupportRequestBatch::factory()->create(['tutor_id' => $tutor->getKey()]);
        $notification = GroupedSupportRequestNotification::forDatabase($batch, [1]);

        $this->assertTrue($notification->shouldSend($tutor, 'database'));
        $this->assertFalse($notification->shouldSend($someoneElse, 'database'));
    }

    private function deliveredBatch(int $requestCount = 1): SupportRequestBatch
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $intern = User::factory()->active()->withRole('stagiaire')->create();
        Internship::factory()->forTutor($tutor)->create(['user_id' => $intern->getKey()]);
        TutorSupportSlot::factory()->on(2, '10:00:00')->create(['tutor_id' => $tutor->getKey()]);

        for ($index = 0; $index < $requestCount; $index++) {
            $task = Task::factory()->create(['assignee_id' => $intern->getKey(), 'created_by' => $intern->getKey()]);
            app(BlockerService::class)->create([
                'origin_type' => 'task',
                'origin_id' => $task->getKey(),
                'problem' => 'Une information manque.',
                'urgency' => BlockerUrgency::Normale,
                'solicited_user_id' => $tutor->getKey(),
                'reported_on' => '2026-08-10',
                'deadline_impact' => "Risque d'un jour.",
                'attempted_action' => 'Consultation des documents.',
            ], $intern);
        }

        app(SupportRequestGroupingService::class)->deliverDue(
            CarbonImmutable::parse('2026-08-11 10:05:00', 'Africa/Niamey')->utc(),
        );

        return SupportRequestBatch::query()->firstOrFail();
    }
}
