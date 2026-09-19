<?php

namespace App\Notifications;

use App\Enums\DailyReportNotificationType;
use App\Enums\DailyReportState;
use App\Enums\UserState;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Services\Platform\CalendarService;
use App\Services\Platform\WhatsAppChannel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class DailyReportReminderNotification extends BaseNotification
{
    /** @param list<string> $channels */
    public function __construct(
        public readonly string $reportDate,
        public readonly DailyReportNotificationType $notificationType,
        public readonly int $recipientId,
        private readonly array $channels,
    ) {
        if ($channels === []) {
            throw new InvalidArgumentException('Le rappel doit utiliser au moins un canal.');
        }

        $this->id = self::stableId($recipientId, $reportDate, $notificationType);
    }

    public static function forDatabase(int $recipientId, string $date, DailyReportNotificationType $type): self
    {
        return new self($date, $type, $recipientId, ['database']);
    }

    public static function forWhatsApp(int $recipientId, string $date, DailyReportNotificationType $type): self
    {
        return new self($date, $type, $recipientId, [WhatsAppChannel::class]);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, report_date: string, notification_type: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'link' => route('daily-reports.today', absolute: false),
            'report_date' => $this->reportDate,
            'notification_type' => $this->notificationType->value,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".route('daily-reports.today');
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User
            || (int) $notifiable->getKey() !== $this->recipientId
            || $notifiable->state !== UserState::Actif
            || ! $notifiable->can('rapport_quotidien.creer')) {
            return false;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $this->reportDate, 'Africa/Niamey');
        if (! $day instanceof CarbonImmutable
            || ! in_array($this->reportDate, app(CalendarService::class)->expectedReportDaysFor($notifiable, $day, $day), true)) {
            return false;
        }

        return ! DailyReport::query()
            ->where('author_id', $notifiable->getKey())
            ->whereDate('report_date', $this->reportDate)
            ->whereIn('state', [
                DailyReportState::Envoye->value,
                DailyReportState::EnRetard->value,
                DailyReportState::Valide->value,
            ])
            ->exists();
    }

    public static function stableId(int $recipientId, string $date, DailyReportNotificationType $type): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            "ptr-staff:daily-report:{$recipientId}:{$date}:{$type->value}",
        )->toString();
    }

    private function message(): string
    {
        return match ($this->notificationType) {
            DailyReportNotificationType::Rappel => "Votre rapport du {$this->reportDate} reste à envoyer avant l'heure limite.",
            DailyReportNotificationType::Retard => "L'heure limite du rapport du {$this->reportDate} est passée. Vous pouvez encore l'envoyer.",
        };
    }
}
