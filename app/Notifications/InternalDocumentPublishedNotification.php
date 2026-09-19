<?php

namespace App\Notifications;

use InvalidArgumentException;

class InternalDocumentPublishedNotification extends BaseNotification
{
    public function __construct(
        public readonly string $title,
        public readonly int $versionNumber,
        public readonly string $link,
    ) {
        if (! str_starts_with($link, '/') || str_starts_with($link, '//')) {
            throw new InvalidArgumentException("Le lien d'une notification doit être interne à l'application.");
        }
    }

    /** @return array{message: string, link: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'link' => $this->link,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".url($this->link);
    }

    private function message(): string
    {
        return "Une nouvelle version du document « {$this->title} » est disponible (version {$this->versionNumber}).";
    }
}
