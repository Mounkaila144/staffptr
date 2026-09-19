<?php

namespace App\Services\Accountability;

use App\Enums\DailyReportNotificationType;
use App\Enums\DailyReportState;
use App\Enums\UserState;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Notifications\DailyReportReminderNotification;
use App\Services\Platform\CalendarService;
use App\Services\Platform\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class DailyReportReminderService
{
    private const TIMEZONE = 'Africa/Niamey';

    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly SettingsService $settingsService,
    ) {}

    public function dispatchDue(?CarbonImmutable $now = null): int
    {
        $localNow = ($now ?? CarbonImmutable::now('UTC'))->setTimezone(self::TIMEZONE);
        $type = $this->dueType($localNow);

        if (! $type instanceof DailyReportNotificationType) {
            return 0;
        }

        $date = $localNow->toDateString();
        $sent = 0;
        $users = User::permission('rapport_quotidien.creer')
            ->where('state', UserState::Actif)
            ->with('person')
            ->get();

        foreach ($users as $recipient) {
            if (! $this->isEligible($recipient, $date)) {
                continue;
            }

            if ($this->dispatchOne($recipient, $date, $type)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function dueType(CarbonImmutable $localNow): ?DailyReportNotificationType
    {
        $deadline = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i',
            $localNow->toDateString().' '.$this->settingsService->reportDeadlineTime(),
            self::TIMEZONE,
        );
        $reminderAt = $deadline->subMinutes($this->settingsService->reportReminderMinutes());

        if ($localNow->greaterThan($deadline)) {
            return DailyReportNotificationType::Retard;
        }

        if ($localNow->greaterThanOrEqualTo($reminderAt)) {
            return DailyReportNotificationType::Rappel;
        }

        return null;
    }

    private function isEligible(User $recipient, string $date): bool
    {
        if (! in_array($date, $this->calendarService->expectedReportDaysFor($recipient, $date, $date), true)) {
            return false;
        }

        return ! DailyReport::query()
            ->where('author_id', $recipient->getKey())
            ->whereDate('report_date', $date)
            ->whereIn('state', [
                DailyReportState::Envoye->value,
                DailyReportState::EnRetard->value,
                DailyReportState::Valide->value,
            ])
            ->exists();
    }

    private function dispatchOne(User $recipient, string $date, DailyReportNotificationType $type): bool
    {
        $notificationId = DailyReportReminderNotification::stableId((int) $recipient->getKey(), $date, $type);
        $claimed = DB::connection($recipient->getConnectionName())->transaction(function () use ($recipient, $date, $type, $notificationId): bool {
            $lockedRecipient = User::query()->whereKey($recipient->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedRecipient->state !== UserState::Actif
                || ! $lockedRecipient->can('rapport_quotidien.creer')
                || ! $this->isEligible($lockedRecipient, $date)
                || DatabaseNotification::query()->whereKey($notificationId)->exists()) {
                return false;
            }

            Notification::sendNow(
                $lockedRecipient,
                DailyReportReminderNotification::forDatabase((int) $lockedRecipient->getKey(), $date, $type),
                ['database'],
            );

            return true;
        });

        if (! $claimed) {
            return false;
        }

        $recipient->notify(DailyReportReminderNotification::forWhatsApp(
            (int) $recipient->getKey(),
            $date,
            $type,
        ));

        return true;
    }
}
