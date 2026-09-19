<?php

namespace App\Notifications;

use App\Services\Platform\WhatsAppChannel;
use App\Services\Platform\WhatsAppPacer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WhatsAppChannel::class];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * Étalement des envois WhatsApp (voir WhatsAppPacer).
     *
     * Laravel appelle cette méthode **par canal** : le canal `database` renvoie
     * toujours `null` et reste donc instantané. Seul WhatsApp est calé, et
     * seulement pour les types qui y sortent réellement — sinon une rafale de
     * notifications purement internes consommerait des créneaux pour rien et
     * retarderait celles qui, elles, partent.
     */
    public function withDelay(object $notifiable, string $channel): ?int
    {
        if ($channel !== WhatsAppChannel::class || ! WhatsAppChannel::delivers(static::class)) {
            return null;
        }

        return app(WhatsAppPacer::class)->reserveDelay();
    }

    /** @return array{message: string, link: string} */
    abstract public function toDatabase(object $notifiable): array;

    abstract public function toWhatsApp(object $notifiable): string;
}
