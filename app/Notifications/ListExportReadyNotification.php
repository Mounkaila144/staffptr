<?php

namespace App\Notifications;

use App\Support\Listing\ListRegistry;

/**
 * « Votre export est disponible » (AC 17).
 *
 * Le message ne contient aucune donnée exportée — ni extrait, ni compteur détaillé —, seulement le
 * nom de la liste. Une notification voyage par WhatsApp ; elle ne doit pas devenir un second canal
 * de diffusion pour ce que le fichier contient.
 */
class ListExportReadyNotification extends BaseNotification
{
    public function __construct(
        public readonly string $listKey,
        public readonly string $filename,
    ) {}

    /** @return array{message: string, link: string, list_key: string, filename: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'link' => $this->link(),
            'list_key' => $this->listKey,
            'filename' => $this->filename,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".url($this->link());
    }

    /** Le lien ramène vers la liste : l'export s'y retélécharge sous les droits du moment. */
    private function link(): string
    {
        return route('listing.index', ['liste' => $this->listKey], absolute: false);
    }

    private function message(): string
    {
        $registry = app(ListRegistry::class);
        $label = $registry->has($this->listKey) ? $registry->get($this->listKey)->label() : $this->listKey;

        return "Votre export de « {$label} » est prêt.";
    }
}
