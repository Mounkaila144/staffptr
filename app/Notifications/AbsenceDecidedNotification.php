<?php

namespace App\Notifications;

use App\Enums\AbsenceState;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Services\Platform\WhatsAppChannel;

/**
 * Prévient le demandeur que son absence a été approuvée ou refusée (décision du propriétaire du
 * 2026-11-01), avec le motif du refus lorsqu'il existe.
 */
class AbsenceDecidedNotification extends BaseNotification
{
    /** @param list<string> $channels */
    public function __construct(
        public readonly int $absenceId,
        public readonly int $requesterId,
        public readonly AbsenceState $decision,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?string $reason,
        private readonly array $channels,
    ) {}

    public static function forDatabase(Absence $absence): self
    {
        return self::make($absence, ['database']);
    }

    public static function forWhatsApp(Absence $absence): self
    {
        return self::make($absence, [WhatsAppChannel::class]);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, absence_id: int} */
    public function toDatabase(object $notifiable): array
    {
        return ['message' => $this->message(), 'link' => $this->link(), 'absence_id' => $this->absenceId];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".url($this->link());
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User || (int) $notifiable->getKey() !== $this->requesterId) {
            return false;
        }

        return Absence::query()
            ->whereKey($this->absenceId)
            ->where('user_id', $this->requesterId)
            ->where('state', $this->decision->value)
            ->exists();
    }

    /** @param list<string> $channels */
    private static function make(Absence $absence, array $channels): self
    {
        return new self(
            (int) $absence->getKey(),
            (int) $absence->user_id,
            $absence->state,
            $absence->start_date->format('d/m/Y'),
            $absence->end_date->format('d/m/Y'),
            $absence->decision_reason,
            $channels,
        );
    }

    private function link(): string
    {
        return route('absences.show', $this->absenceId, absolute: false);
    }

    private function message(): string
    {
        $period = $this->startDate === $this->endDate
            ? "du {$this->startDate}"
            : "du {$this->startDate} au {$this->endDate}";

        if ($this->decision === AbsenceState::Approuvee) {
            return "Votre demande d'absence {$period} a été approuvée.";
        }

        $message = "Votre demande d'absence {$period} a été refusée.";

        return $this->reason !== null && $this->reason !== '' ? "{$message} Motif : {$this->reason}" : $message;
    }
}
