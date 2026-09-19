<?php

namespace App\Notifications;

use App\Enums\ObjectiveState;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Services\Platform\WhatsAppChannel;
use Ramsey\Uuid\Uuid;

/**
 * « Objectif proche de l'échéance » — l'un des onze événements de FR31.
 *
 * Le message parle de contribution et non de surveillance : il rappelle une échéance, il ne
 * reproche rien et n'établit aucun classement entre personnes (SOC-10, FR82).
 *
 * L'identité est `objectif + destinataire + date civile` : rejouer la tâche le même jour ne crée
 * aucun doublon, ni en base ni sur WhatsApp (AC 35, AC 44).
 */
class ObjectiveDeadlineNotification extends BaseNotification
{
    /** @param list<string> $channels */
    public function __construct(
        public readonly int $objectiveId,
        public readonly int $recipientId,
        public readonly string $objectiveTitle,
        public readonly string $dueDate,
        public readonly int $daysLeft,
        public readonly string $reminderDate,
        private readonly array $channels,
    ) {
        $this->id = self::stableId($objectiveId, $recipientId, $reminderDate);
    }

    public static function forDatabase(Objective $objective, int $recipientId, int $daysLeft, string $reminderDate): self
    {
        return self::build($objective, $recipientId, $daysLeft, $reminderDate, ['database']);
    }

    public static function forWhatsApp(Objective $objective, int $recipientId, int $daysLeft, string $reminderDate): self
    {
        return self::build($objective, $recipientId, $daysLeft, $reminderDate, [WhatsAppChannel::class]);
    }

    /** @param list<string> $channels */
    private static function build(Objective $objective, int $recipientId, int $daysLeft, string $reminderDate, array $channels): self
    {
        return new self(
            (int) $objective->getKey(),
            $recipientId,
            (string) $objective->title,
            $objective->due_date->toDateString(),
            $daysLeft,
            $reminderDate,
            $channels,
        );
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, objective_id: int, due_date: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'link' => $this->link(),
            'objective_id' => $this->objectiveId,
            'due_date' => $this->dueDate,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".url($this->link());
    }

    /**
     * L'objectif a pu être atteint, annulé ou sortir du périmètre du destinataire entre la mise en
     * file et l'envoi. Le contenu n'est alors pas exposé et rien n'est émis (AC 37).
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User || (int) $notifiable->getKey() !== $this->recipientId) {
            return false;
        }

        return Objective::query()
            ->whereKey($this->objectiveId)
            ->where('user_id', $this->recipientId)
            ->whereIn('state', self::openStates())
            ->exists();
    }

    /** @return list<string> */
    public static function openStates(): array
    {
        return [
            ObjectiveState::Valide->value,
            ObjectiveState::EnCours->value,
            ObjectiveState::Bloque->value,
        ];
    }

    public static function stableId(int $objectiveId, int $recipientId, string $reminderDate): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            "ptr-staff:objective-deadline:{$objectiveId}:{$recipientId}:{$reminderDate}",
        )->toString();
    }

    /** Lien direct vers l'objectif : consulter puis agir tient en trois interactions (AC 34). */
    private function link(): string
    {
        return route('objectives.show', $this->objectiveId, absolute: false);
    }

    private function message(): string
    {
        return match (true) {
            $this->daysLeft <= 0 => "L'objectif « {$this->objectiveTitle} » arrive à échéance aujourd'hui.",
            $this->daysLeft === 1 => "L'objectif « {$this->objectiveTitle} » arrive à échéance demain.",
            default => "L'objectif « {$this->objectiveTitle} » arrive à échéance dans {$this->daysLeft} jours.",
        };
    }
}
