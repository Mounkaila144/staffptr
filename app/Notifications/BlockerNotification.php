<?php

namespace App\Notifications;

use App\Enums\BlockerUrgency;
use App\Models\Accountability\Blocker;
use App\Models\Identity\User;
use App\Services\Platform\WhatsAppChannel;

class BlockerNotification extends BaseNotification
{
    /** @param list<string> $channels */
    public function __construct(public readonly int $blockerId, public readonly int $recipientId, public readonly BlockerUrgency $urgency, private readonly array $channels) {}

    public static function forDatabase(Blocker $blocker): self
    {
        return new self((int) $blocker->getKey(), (int) $blocker->solicited_user_id, $blocker->urgency, ['database']);
    }

    public static function forWhatsApp(Blocker $blocker): self
    {
        return new self((int) $blocker->getKey(), (int) $blocker->solicited_user_id, $blocker->urgency, [WhatsAppChannel::class]);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, blocker_id: int} */
    public function toDatabase(object $notifiable): array
    {
        return ['message' => $this->message(), 'link' => route('blockers.index', absolute: false), 'blocker_id' => $this->blockerId];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".route('blockers.index');
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof User && (int) $notifiable->getKey() === $this->recipientId
            && Blocker::query()->whereKey($this->blockerId)->where('solicited_user_id', $this->recipientId)->exists();
    }

    private function message(): string
    {
        return $this->urgency === BlockerUrgency::Urgente
            ? 'Blocage urgent : votre aide est demandée immédiatement.'
            : 'Votre aide est demandée sur un nouveau blocage.';
    }
}
