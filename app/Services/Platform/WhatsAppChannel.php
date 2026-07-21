<?php

namespace App\Services\Platform;

use App\Models\Identity\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Notification;
use LogicException;

class WhatsAppChannel
{
    public function __construct(private readonly EvolutionApiClient $evolutionApiClient) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof BaseNotification) {
            throw new LogicException('Le canal WhatsApp attend un utilisateur et une notification PTR Staff.');
        }

        $this->evolutionApiClient->sendText(
            $notifiable->phone,
            $notification->toWhatsApp($notifiable),
        );
    }
}
