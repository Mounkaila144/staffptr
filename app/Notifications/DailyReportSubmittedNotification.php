<?php

namespace App\Notifications;

use App\Enums\DailyReportState;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Services\Platform\WhatsAppChannel;

class DailyReportSubmittedNotification extends BaseNotification
{
    /** @param list<string> $channels */
    public function __construct(
        public readonly int $reportId,
        public readonly int $reviewerId,
        public readonly string $authorName,
        private readonly array $channels,
    ) {}

    public static function forDatabase(DailyReport $report, User $reviewer): self
    {
        return new self(
            (int) $report->getKey(),
            (int) $reviewer->getKey(),
            self::authorName($report),
            ['database'],
        );
    }

    public static function forWhatsApp(DailyReport $report, User $reviewer): self
    {
        return new self(
            (int) $report->getKey(),
            (int) $reviewer->getKey(),
            self::authorName($report),
            [WhatsAppChannel::class],
        );
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, report_id: int} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'link' => route('daily-report-reviews.show', $this->reportId, absolute: false),
            'report_id' => $this->reportId,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n"
            .route('daily-report-reviews.show', $this->reportId);
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User || (int) $notifiable->getKey() !== $this->reviewerId) {
            return false;
        }

        $report = DailyReport::query()->find($this->reportId);

        return $report instanceof DailyReport
            && in_array($report->state, [DailyReportState::Envoye, DailyReportState::EnRetard], true)
            && $notifiable->can('review', $report);
    }

    private static function authorName(DailyReport $report): string
    {
        $report->loadMissing('author.person');

        return $report->author->person->full_name;
    }

    private function message(): string
    {
        return "Le rapport quotidien de {$this->authorName} attend votre validation.";
    }
}
