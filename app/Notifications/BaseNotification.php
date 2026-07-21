<?php

namespace App\Notifications;

use App\Services\Platform\WhatsAppChannel;
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

    /** @return array{message: string, link: string} */
    abstract public function toDatabase(object $notifiable): array;

    abstract public function toWhatsApp(object $notifiable): string;
}
