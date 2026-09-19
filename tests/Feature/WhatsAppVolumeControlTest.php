<?php

namespace Tests\Feature;

use App\Enums\DailyReportNotificationType;
use App\Models\Identity\User;
use App\Notifications\DailyReportReminderNotification;
use App\Notifications\GenericLinkedNotification;
use App\Notifications\InternalDocumentPublishedNotification;
use App\Notifications\ListExportReadyNotification;
use App\Services\Platform\WhatsAppChannel;
use App\Services\Platform\WhatsAppPacer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\IdentityTestCase;

/**
 * Maîtrise du volume WhatsApp.
 *
 * L'application joint WhatsApp par un client non officiel : une rafale de
 * messages identiques expose le numéro à un blocage, et ce numéro porte aussi
 * le code de réinitialisation de mot de passe — seul chemin de récupération de
 * compte. Ces tests protègent les deux garde-fous : la liste blanche des types
 * autorisés, et l'étalement des envois.
 */
class WhatsAppVolumeControlTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.key' => 'evolution-test-key',
            'services.evolution.instance' => 'ptr-test',
            'services.evolution.allow_real_delivery' => true,
        ]);

        Cache::forget('whatsapp:pace:next-slot');
    }

    public function test_only_whitelisted_types_reach_whatsapp(): void
    {
        $this->assertTrue(
            WhatsAppChannel::delivers(DailyReportReminderNotification::class),
            'Le rappel de rapport quotidien est la raison d’être du canal.',
        );

        foreach ([
            InternalDocumentPublishedNotification::class,
            ListExportReadyNotification::class,
            GenericLinkedNotification::class,
        ] as $internalOnly) {
            $this->assertFalse(
                WhatsAppChannel::delivers($internalOnly),
                "{$internalOnly} doit rester dans l’application.",
            );
        }
    }

    public function test_a_type_outside_the_whitelist_sends_no_http_request(): void
    {
        Http::fake();

        $user = User::factory()->active()->withRole('employe')->create();

        app(WhatsAppChannel::class)->send(
            $user,
            new GenericLinkedNotification('Une demande de tâche vous attend.', '/taches'),
        );

        Http::assertNothingSent();
    }

    public function test_a_whitelisted_type_does_send(): void
    {
        Http::fake([
            '*/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
            '*/message/sendText/*' => Http::response([], 201),
        ]);

        $user = User::factory()->active()->withRole('employe')->create();

        app(WhatsAppChannel::class)->send(
            $user,
            DailyReportReminderNotification::forWhatsApp(
                (int) $user->getKey(),
                '2026-08-17',
                DailyReportNotificationType::Rappel,
            ),
        );

        Http::assertSent(static fn ($request): bool => str_contains($request->url(), '/message/sendText/'));
    }

    /**
     * Le premier envoi part tout de suite ; les suivants s'espacent. C'est
     * exactement ce qui transforme une rafale de cinquante messages en un flux
     * régulier.
     */
    public function test_the_pacer_spreads_consecutive_sends(): void
    {
        config(['notifications.whatsapp.pace.per_minute' => 4]);

        $pacer = app(WhatsAppPacer::class);

        $this->assertSame(0, $pacer->reserveDelay(), 'Le premier message ne doit pas attendre.');
        $this->assertSame(15, $pacer->reserveDelay());
        $this->assertSame(30, $pacer->reserveDelay());
        $this->assertSame(45, $pacer->reserveDelay());
    }

    public function test_the_pacer_never_delays_beyond_the_configured_ceiling(): void
    {
        config([
            'notifications.whatsapp.pace.per_minute' => 4,
            'notifications.whatsapp.pace.max_delay_seconds' => 30,
        ]);

        $pacer = app(WhatsAppPacer::class);

        for ($i = 0; $i < 20; $i++) {
            $this->assertLessThanOrEqual(30, $pacer->reserveDelay());
        }
    }

    /**
     * Cinquante rappels ne doivent plus tenir dans une minute. C'est la
     * situation réelle : une promotion de stagiaires, un seul créneau limite.
     */
    public function test_fifty_reminders_no_longer_fit_in_a_single_minute(): void
    {
        config(['notifications.whatsapp.pace.per_minute' => 4]);

        $pacer = app(WhatsAppPacer::class);
        $last = 0;

        for ($i = 0; $i < 50; $i++) {
            $last = $pacer->reserveDelay();
        }

        $this->assertGreaterThanOrEqual(
            600,
            $last,
            'Cinquante envois doivent s’étaler sur au moins dix minutes.',
        );
    }

    /**
     * Le canal interne ne doit jamais payer le prix de l'étalement : une
     * notification doit rester visible immédiatement dans l'application.
     */
    public function test_the_database_channel_is_never_delayed(): void
    {
        $user = User::factory()->active()->withRole('employe')->create();

        $notification = DailyReportReminderNotification::forDatabase(
            (int) $user->getKey(),
            '2026-08-17',
            DailyReportNotificationType::Rappel,
        );

        $this->assertNull($notification->withDelay($user, 'database'));
    }

    public function test_a_type_outside_the_whitelist_consumes_no_pacing_slot(): void
    {
        config(['notifications.whatsapp.pace.per_minute' => 4]);

        $user = User::factory()->active()->withRole('employe')->create();
        $internal = new GenericLinkedNotification('Une demande de tâche vous attend.', '/taches');

        $this->assertNull($internal->withDelay($user, WhatsAppChannel::class));

        // Le créneau reste libre pour un type qui, lui, part vraiment.
        $this->assertSame(0, app(WhatsAppPacer::class)->reserveDelay());
    }
}
