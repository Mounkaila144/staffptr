<?php

namespace App\Notifications;

use App\Models\Accountability\SupportRequestBatch;
use App\Models\Identity\User;
use App\Services\Platform\WhatsAppChannel;

/**
 * Notification unique portant les demandes non urgentes accumulées jusqu'au créneau (AC 35).
 *
 * Chaque demande reste un objet distinct : la charge utile porte la liste complète des
 * identifiants, jamais un résumé fusionné (AC 38).
 */
class GroupedSupportRequestNotification extends BaseNotification
{
    /**
     * @param  list<int>  $requestIds
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly int $batchId,
        public readonly int $recipientId,
        public readonly array $requestIds,
        private readonly array $channels,
    ) {}

    /** @param list<int> $requestIds */
    public static function forDatabase(SupportRequestBatch $batch, array $requestIds): self
    {
        return new self((int) $batch->getKey(), (int) $batch->tutor_id, $requestIds, ['database']);
    }

    /** @param list<int> $requestIds */
    public static function forWhatsApp(SupportRequestBatch $batch, array $requestIds): self
    {
        return new self((int) $batch->getKey(), (int) $batch->tutor_id, $requestIds, [WhatsAppChannel::class]);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, batch_id: int, request_ids: list<int>, request_count: int} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'link' => route('blockers.index', absolute: false),
            'batch_id' => $this->batchId,
            // Les demandes restent distinctes et adressables une par une (AC 38).
            'request_ids' => $this->requestIds,
            'request_count' => count($this->requestIds),
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".route('blockers.index');
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof User && (int) $notifiable->getKey() === $this->recipientId;
    }

    private function message(): string
    {
        $count = count($this->requestIds);

        return $count === 1
            ? 'Une demande de votre stagiaire attend votre créneau de suivi.'
            : "{$count} demandes de vos stagiaires attendent votre créneau de suivi.";
    }
}
