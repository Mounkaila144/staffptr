<?php

namespace App\Notifications;

use App\Enums\DailyReportDecisionType;
use App\Enums\DailyReportState;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Services\Platform\WhatsAppChannel;

class DailyReportDecisionNotification extends BaseNotification
{
    /** @param list<string> $channels */
    public function __construct(
        public readonly int $reportId,
        public readonly int $authorId,
        public readonly DailyReportDecisionType $decision,
        public readonly ?string $reason,
        private readonly array $channels,
    ) {}

    public static function forDatabase(DailyReport $report, DailyReportDecisionType $decision, ?string $reason): self
    {
        return new self((int) $report->getKey(), (int) $report->author_id, $decision, $reason, ['database']);
    }

    public static function forWhatsApp(DailyReport $report, DailyReportDecisionType $decision, ?string $reason): self
    {
        return new self((int) $report->getKey(), (int) $report->author_id, $decision, $reason, [WhatsAppChannel::class]);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, report_id: int} */
    public function toDatabase(object $notifiable): array
    {
        return ['message' => $this->message(), 'link' => route('daily-reports.today', absolute: false), 'report_id' => $this->reportId];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".route('daily-reports.today');
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User || (int) $notifiable->getKey() !== $this->authorId) {
            return false;
        }

        $state = $this->decision === DailyReportDecisionType::Valider
            ? DailyReportState::Valide
            : DailyReportState::Retourne;

        return DailyReport::query()->whereKey($this->reportId)->where('author_id', $this->authorId)->where('state', $state)->exists();
    }

    private function message(): string
    {
        if ($this->decision === DailyReportDecisionType::Valider) {
            return 'Votre rapport quotidien a été validé.';
        }

        return 'Votre rapport quotidien vous a été retourné. Motif : '.$this->reason;
    }
}
