<?php

namespace App\Notifications;

use App\Models\Finance\MonthlyReport;
use App\Models\Identity\User;
use App\Services\Platform\WhatsAppChannel;
use Carbon\CarbonImmutable;
use Ramsey\Uuid\Uuid;

class FinancialReportPreparationReminderNotification extends BaseNotification
{
    /**
     * Le canal est choisi à la construction, comme pour les autres rappels du produit : la ligne
     * `database` est écrite sous transaction, puis WhatsApp est mis en file séparément. Sans cette
     * séparation, le travail en file réécrirait la ligne `database` déjà créée (story 9.1 AC 32,
     * AC 35, AC 36).
     *
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly CarbonImmutable $month,
        public readonly string $phase,
        public readonly int $recipientId,
        private readonly array $channels = ['database', WhatsAppChannel::class],
    ) {
        $this->id = Uuid::uuid5(Uuid::NAMESPACE_URL, "ptr-staff:financial-report:{$month->format('Y-m')}:{$recipientId}:{$phase}")->toString();
    }

    public static function forDatabase(CarbonImmutable $month, string $phase, int $recipientId): self
    {
        return new self($month, $phase, $recipientId, ['database']);
    }

    public static function forWhatsApp(CarbonImmutable $month, string $phase, int $recipientId): self
    {
        return new self($month, $phase, $recipientId, [WhatsAppChannel::class]);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, month: string, phase: string} */
    public function toDatabase(object $notifiable): array
    {
        $label = $this->month->locale('fr')->translatedFormat('F Y');

        return [
            'message' => $this->phase === 'retard' ? "Le rapport financier de {$label} a dépassé l’échéance du 5." : "Le rapport financier de {$label} doit être préparé avant le 5.",
            'link' => route('financial-reports.index', [], false), 'month' => $this->month->format('Y-m'), 'phase' => $this->phase,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return (string) $this->toDatabase($notifiable)['message'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof User && (int) $notifiable->getKey() === $this->recipientId
            && $notifiable->can('rapport_financier.preparer')
            && MonthlyReport::query()->whereDate('month', $this->month->toDateString())->doesntExist();
    }
}
