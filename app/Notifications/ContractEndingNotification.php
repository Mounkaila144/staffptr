<?php

namespace App\Notifications;

use App\Enums\UserState;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Ramsey\Uuid\Uuid;

/**
 * « Fin de contrat ou de stage proche » — onzième événement de FR31 (AC 32, AC 33).
 *
 * L'échéance est détectée par `ContractEndingService`, exposé par la story 3.2 mais qui n'émettait
 * rien faute de centre de notifications à ce jalon. Cette classe est le consommateur qui lui
 * manquait : c'est ici que la détection devient une notification.
 *
 * Le message reste factuel et sans jugement — une fin de contrat est une échéance administrative,
 * pas une sanction (SOC-10).
 */
class ContractEndingNotification extends BaseNotification
{
    /** @param list<string> $channels */
    public function __construct(
        public readonly int $recipientId,
        public readonly int $concernedUserId,
        public readonly int $concernedPersonId,
        public readonly string $concernedName,
        public readonly string $endDate,
        public readonly int $daysLeft,
        public readonly string $reminderDate,
        public readonly bool $isSelf,
        private readonly array $channels,
    ) {
        $this->id = self::stableId($recipientId, $concernedUserId, $reminderDate);
    }

    /** @param list<string> $channels */
    public static function build(
        int $recipientId,
        User $concerned,
        int $daysLeft,
        string $reminderDate,
        array $channels,
    ): self {
        return new self(
            $recipientId,
            (int) $concerned->getKey(),
            (int) $concerned->person_id,
            $concerned->person->full_name,
            $concerned->contract_end_date instanceof CarbonImmutable
                ? $concerned->contract_end_date->toDateString()
                : (string) $concerned->contract_end_date,
            $daysLeft,
            $reminderDate,
            $recipientId === (int) $concerned->getKey(),
            $channels,
        );
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, concerned_user_id: int, end_date: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'link' => $this->link(),
            'concerned_user_id' => $this->concernedUserId,
            'end_date' => $this->endDate,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".url($this->link());
    }

    /**
     * Le compte concerné a pu quitter le périmètre du destinataire, ou l'échéance être repoussée,
     * entre la mise en file et l'envoi : rien n'est alors exposé (AC 37).
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User || (int) $notifiable->getKey() !== $this->recipientId) {
            return false;
        }

        if (! $this->isSelf && ! $notifiable->can('fiche.consulter')) {
            return false;
        }

        return User::query()
            ->whereKey($this->concernedUserId)
            ->where('state', UserState::Actif)
            ->whereDate('contract_end_date', $this->endDate)
            ->exists();
    }

    public static function stableId(int $recipientId, int $concernedUserId, string $reminderDate): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            "ptr-staff:contract-ending:{$recipientId}:{$concernedUserId}:{$reminderDate}",
        )->toString();
    }

    /**
     * Le destinataire concerné va vers sa propre fiche ; la direction va vers la fiche du compte.
     * Dans les deux cas, l'action attendue est à un clic de la notification (AC 34).
     */
    private function link(): string
    {
        return route('people.show', $this->concernedPersonId, absolute: false);
    }

    private function message(): string
    {
        $horizon = match (true) {
            $this->daysLeft <= 0 => "aujourd'hui",
            $this->daysLeft === 1 => 'demain',
            default => "dans {$this->daysLeft} jours",
        };

        if ($this->isSelf) {
            return "Votre contrat ou stage prend fin {$horizon} ({$this->endDate}).";
        }

        return "Le contrat ou stage de {$this->concernedName} prend fin {$horizon} ({$this->endDate}).";
    }
}
