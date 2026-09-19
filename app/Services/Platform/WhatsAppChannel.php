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

        // Le filtre est ici, et pas dans `via()`, parce que plusieurs
        // notifications construisent leurs canaux à l'appel (`forDatabase()`,
        // `forWhatsApp()`). Ce canal est le seul point par lequel *tout* envoi
        // WhatsApp passe : le placer ailleurs laisserait des chemins ouverts,
        // y compris pour les notifications écrites plus tard.
        if (! self::delivers($notification::class)) {
            return;
        }

        $this->evolutionApiClient->sendText(
            $notifiable->phone,
            $notification->toWhatsApp($notifiable),
        );
    }

    /**
     * Ce type de notification sort-il sur WhatsApp ?
     *
     * La liste blanche vit dans `config/notifications.php`, qui porte le
     * raisonnement de chaque inscription. Un type absent reste notifié dans
     * l'application : il n'est pas perdu, il ne quitte pas le produit.
     */
    public static function delivers(string $notificationClass): bool
    {
        /** @var list<string> $enabled */
        $enabled = config('notifications.whatsapp.enabled', []);

        return in_array($notificationClass, $enabled, true);
    }
}
