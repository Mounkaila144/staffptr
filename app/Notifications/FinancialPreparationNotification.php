<?php

namespace App\Notifications;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Services\Platform\WhatsAppChannel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * « Rapprochement ou rapport financier à préparer » — événement de FR31 (AC 32, AC 35).
 *
 * Un seul type de notification pour les deux échéances, parce que c'est le même geste métier au
 * même moment du mois, avec deux destinations différentes.
 */
class FinancialPreparationNotification extends BaseNotification
{
    public const KIND_RECONCILIATION = 'reconciliation';

    public const KIND_MONTHLY_REPORT = 'monthly_report';

    /** @param list<string> $channels */
    public function __construct(
        public readonly int $recipientId,
        public readonly string $kind,
        public readonly string $month,
        public readonly string $reminderDate,
        private readonly array $channels,
    ) {
        if (! in_array($kind, [self::KIND_RECONCILIATION, self::KIND_MONTHLY_REPORT], true)) {
            throw new InvalidArgumentException("Type d'échéance financière inconnu : {$kind}.");
        }

        $this->id = self::stableId($recipientId, $kind, $month);
    }

    public static function forDatabase(int $recipientId, string $kind, string $month, string $reminderDate): self
    {
        return new self($recipientId, $kind, $month, $reminderDate, ['database']);
    }

    public static function forWhatsApp(int $recipientId, string $kind, string $month, string $reminderDate): self
    {
        return new self($recipientId, $kind, $month, $reminderDate, [WhatsAppChannel::class]);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, kind: string, month: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'link' => $this->link(),
            'kind' => $this->kind,
            'month' => $this->month,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".url($this->link());
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof User
            && (int) $notifiable->getKey() === $this->recipientId
            && $notifiable->state === UserState::Actif
            && $notifiable->can($this->permission());
    }

    /**
     * L'identité ne comprend pas la date de relance : une échéance mensuelle ne se rappelle
     * qu'une fois par mois et par destinataire, même si la tâche tourne tous les jours (AC 35).
     */
    public static function stableId(int $recipientId, string $kind, string $month): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            "ptr-staff:financial-preparation:{$recipientId}:{$kind}:{$month}",
        )->toString();
    }

    public function permission(): string
    {
        return $this->kind === self::KIND_RECONCILIATION
            ? 'rapprochement.preparer'
            : 'rapport_financier.preparer';
    }

    private function link(): string
    {
        return $this->kind === self::KIND_RECONCILIATION
            ? route('reconciliations.index', absolute: false)
            : route('financial-reports.index', absolute: false);
    }

    private function message(): string
    {
        $monthLabel = CarbonImmutable::createFromFormat('!Y-m-d', $this->month, 'Africa/Niamey')->format('m/Y');

        return $this->kind === self::KIND_RECONCILIATION
            ? "Le rapprochement bancaire du mois {$monthLabel} reste à préparer."
            : "Le rapport financier du mois {$monthLabel} reste à préparer.";
    }
}
