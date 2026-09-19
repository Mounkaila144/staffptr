<?php

namespace App\Notifications;

use App\Enums\AlertLevel;
use App\Models\Finance\CorrectionPlan;
use App\Models\Identity\User;
use App\Services\Platform\WhatsAppChannel;
use Carbon\CarbonImmutable;
use Ramsey\Uuid\Uuid;

/**
 * Relance de `direction` tant qu'aucun plan correctif n'existe pour le mois passé en orange
 * (AC 11, AC 16, FR163).
 *
 * L'identité de la notification est stable — destinataire, mois concerné, date civile de relance —
 * si bien qu'une tâche rejouée le même jour ne peut pas créer de doublon (AC 35, AC 44).
 */
class CorrectionPlanReminderNotification extends BaseNotification
{
    /** @param list<string> $channels */
    public function __construct(
        public readonly int $recipientId,
        public readonly string $month,
        public readonly string $reminderDate,
        public readonly string $dueLabel,
        private readonly array $channels,
    ) {
        $this->id = self::stableId($recipientId, $month, $reminderDate);
    }

    public static function forDatabase(int $recipientId, string $month, string $reminderDate, string $dueLabel): self
    {
        return new self($recipientId, $month, $reminderDate, $dueLabel, ['database']);
    }

    public static function forWhatsApp(int $recipientId, string $month, string $reminderDate, string $dueLabel): self
    {
        return new self($recipientId, $month, $reminderDate, $dueLabel, [WhatsAppChannel::class]);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, month: string, alert_level: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'link' => $this->link(),
            'month' => $this->month,
            'alert_level' => AlertLevel::Orange->value,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".url($this->link());
    }

    /**
     * Le plan a pu être enregistré entre la mise en file et l'envoi : la relance s'éteint alors
     * d'elle-même, sans message inutile (AC 16).
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User || (int) $notifiable->getKey() !== $this->recipientId) {
            return false;
        }

        return ! CorrectionPlan::query()
            ->whereDate('month', $this->month)
            ->exists();
    }

    public static function stableId(int $recipientId, string $month, string $reminderDate): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            "ptr-staff:correction-plan-reminder:{$recipientId}:{$month}:{$reminderDate}",
        )->toString();
    }

    /**
     * Un lien direct vers le formulaire de plan : ouvrir la notification puis enregistrer le plan
     * tient en trois interactions au plus (AC 34).
     */
    private function link(): string
    {
        return route('correction-plans.create', ['mois' => $this->month], absolute: false);
    }

    private function message(): string
    {
        $monthLabel = CarbonImmutable::createFromFormat('!Y-m-d', $this->month, 'Africa/Niamey')->format('m/Y');

        return sprintf(
            "Niveau d'alerte %s pour le mois %s : le plan correctif reste à enregistrer (%s).",
            AlertLevel::Orange->label(),
            $monthLabel,
            $this->dueLabel,
        );
    }
}
