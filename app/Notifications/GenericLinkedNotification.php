<?php

namespace App\Notifications;

use InvalidArgumentException;

class GenericLinkedNotification extends BaseNotification
{
    public function __construct(
        public readonly string $message,
        public readonly string $link,
    ) {
        if (trim($message) === '') {
            throw new InvalidArgumentException('Le message de notification est obligatoire.');
        }

        if (! str_starts_with($link, '/') || str_starts_with($link, '//')) {
            throw new InvalidArgumentException("Le lien d'une notification doit être interne à l'application.");
        }
    }

    /** @return array{message: string, link: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'link' => $this->link,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message}\n".url($this->link);
    }
}
