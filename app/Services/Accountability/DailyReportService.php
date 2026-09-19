<?php

namespace App\Services\Accountability;

use App\Enums\DailyReportState;
use App\Enums\UserState;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Notifications\DailyReportSubmittedNotification;
use App\Services\Platform\AttachmentService;
use App\Services\Platform\CalendarService;
use App\Services\Platform\SettingsService;
use App\Services\Work\TodayTaskService;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class DailyReportService
{
    private const TIMEZONE = 'Africa/Niamey';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AccountabilityDashboardService $dashboardService,
        private readonly AttachmentService $attachmentService,
        private readonly CalendarService $calendarService,
        private readonly SettingsService $settingsService,
        private readonly TodayTaskService $todayTaskService,
    ) {}

    /**
     * @return array{date: string, expected: bool, unavailable_message: string|null, planned_task: string, report: array<string, mixed>|null}
     */
    public function formFor(User $actor, ?CarbonImmutable $now = null): array
    {
        $day = ($now ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE)->startOfDay();
        $date = $day->toDateString();
        $expected = $this->isExpected($actor, $day);
        $report = DailyReport::query()
            ->with(['currentVersion.attachment'])
            ->where('author_id', $actor->getKey())
            ->whereDate('report_date', $date)
            ->first();

        return [
            'date' => $date,
            'expected' => $expected,
            'unavailable_message' => $expected ? null : "Aucun rapport n'est attendu aujourd'hui : ce jour est fermé ou couvert par une absence approuvée.",
            'planned_task' => $this->todayTaskService->forUser($actor, $day)->pluck('title')->implode("\n"),
            'report' => $report instanceof DailyReport ? $this->reportPayload($report) : null,
        ];
    }

    /**
     * @param  array{planned_task: string, achieved_result: string, evidence_link?: string|null, attachment_ulid?: string|null, blocker_present: bool, blocker_details?: string|null, next_action: string, help_requested: bool, help_details?: string|null, lateness_explanation?: string|null, correction_reason?: string|null, idempotency_key: string}  $data
     */
    public function submit(array $data, User $actor, ?CarbonImmutable $now = null): DailyReport
    {
        $submittedAt = ($now ?? CarbonImmutable::now('UTC'))->utc();
        $day = $submittedAt->setTimezone(self::TIMEZONE)->startOfDay();

        if (! $this->isExpected($actor, $day)) {
            throw ValidationException::withMessages([
                'report' => "Aucun rapport n'est attendu pour cette date.",
            ]);
        }

        $subject = new DailyReport;

        $write = function () use ($data, $actor, $submittedAt, $day): DailyReport {
            $existingVersion = DailyReportVersion::query()
                ->with('report')
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existingVersion instanceof DailyReportVersion) {
                if ($existingVersion->author_id !== $actor->getKey()) {
                    throw ValidationException::withMessages(['idempotency_key' => "Cette clé d'envoi est déjà utilisée."]);
                }

                return $existingVersion->report->load(['currentVersion.attachment', 'versions']);
            }

            $report = DailyReport::query()
                ->where('author_id', $actor->getKey())
                ->whereDate('report_date', $day->toDateString())
                ->lockForUpdate()
                ->first();

            if (! $report instanceof DailyReport) {
                $report = new DailyReport;
                $report->fill([
                    'author_id' => $actor->getKey(),
                    'report_date' => $day->toDateString(),
                    'state' => DailyReportState::Brouillon,
                ]);
                $report->saveOrFail();
            }

            $versionNumber = ((int) $report->versions()->max('version_number')) + 1;
            if ($versionNumber > 1 && trim((string) ($data['correction_reason'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'correction_reason' => 'Indiquez le motif de la correction.',
                ]);
            }
            $version = new DailyReportVersion;
            $version->fill([
                'daily_report_id' => $report->getKey(),
                'version_number' => $versionNumber,
                'author_id' => $actor->getKey(),
                'idempotency_key' => $data['idempotency_key'],
                'planned_task' => $data['planned_task'],
                'achieved_result' => $data['achieved_result'],
                'evidence_link' => $data['evidence_link'] ?? null,
                'blocker_present' => $data['blocker_present'],
                'blocker_details' => $data['blocker_present'] ? ($data['blocker_details'] ?? null) : null,
                'next_action' => $data['next_action'],
                'help_requested' => $data['help_requested'],
                'help_details' => $data['help_requested'] ? ($data['help_details'] ?? null) : null,
                'correction_reason' => $data['correction_reason'] ?? null,
            ]);
            $version->saveOrFail();

            $attachment = $this->stagedAttachment($data['attachment_ulid'] ?? null, $actor);
            if ($attachment instanceof Attachment) {
                $this->attachmentService->attachExisting($attachment, $version, $actor);
            }

            $oldValues = [
                'state' => $report->state->value,
                'submitted_at' => $report->submitted_at?->toISOString(),
            ];
            // L'heure d'envoi est celle de la première soumission : une correction ultérieure
            // crée une nouvelle version mais ne rend pas rétroactivement le rapport en retard.
            $firstSubmittedAt = $report->submitted_at ?? $submittedAt;
            $state = $firstSubmittedAt->lessThanOrEqualTo($this->deadlineFor($day))
                ? DailyReportState::Envoye
                : DailyReportState::EnRetard;
            $report->fill([
                'state' => $state,
                'submitted_at' => $firstSubmittedAt,
                'lateness_explanation' => $state === DailyReportState::EnRetard
                    ? ($data['lateness_explanation'] ?? $report->lateness_explanation)
                    : null,
            ]);
            $report->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
                auditable: $report,
                action: $versionNumber === 1 ? 'daily_report_submitted' : 'daily_report_corrected',
                oldValues: $oldValues,
                newValues: [
                    'state' => $state->value,
                    'submitted_at' => $firstSubmittedAt->toISOString(),
                    'version_number' => $versionNumber,
                ],
                reason: $data['correction_reason'] ?? null,
            );

            return $report->load(['currentVersion.attachment', 'versions']);
        };

        try {
            $report = DB::connection($subject->getConnectionName())->transaction($write, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Deux envois identiques simultanés : la contrainte d'unicité a protégé la base.
            // Le perdant de la course rouvre le rapport déjà enregistré au lieu d'échouer (AC 1 et 9).
            $report = $this->reportForIdempotencyKey($data['idempotency_key'], $actor)
                ?? throw $exception;
        }

        $this->dashboardService->invalidate($actor, $day);
        $this->notifyReviewers($report, $actor);

        return $report;
    }

    private function reportForIdempotencyKey(string $idempotencyKey, User $actor): ?DailyReport
    {
        $version = DailyReportVersion::query()
            ->with('report')
            ->where('idempotency_key', $idempotencyKey)
            ->where('author_id', $actor->getKey())
            ->first();

        return $version instanceof DailyReportVersion
            ? $version->report->load(['currentVersion.attachment', 'versions'])
            : null;
    }

    private function notifyReviewers(DailyReport $report, User $author): void
    {
        $manager = $author->manager;
        $reviewers = $manager instanceof User && $manager->can('review', $report)
            ? collect([$manager])
            : User::query()
                ->where('state', UserState::Actif)
                ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'direction'))
                ->whereKeyNot($author->getKey())
                ->get()
                ->filter(fn (User $reviewer): bool => $reviewer->can('review', $report));

        foreach ($reviewers as $reviewer) {
            Notification::sendNow($reviewer, DailyReportSubmittedNotification::forDatabase($report, $reviewer), ['database']);
            $reviewer->notify(DailyReportSubmittedNotification::forWhatsApp($report, $reviewer));
        }
    }

    private function isExpected(User $actor, CarbonImmutable $day): bool
    {
        return in_array(
            $day->toDateString(),
            $this->calendarService->expectedReportDaysFor($actor, $day, $day),
            true,
        );
    }

    private function deadlineFor(CarbonImmutable $day): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            '!Y-m-d H:i',
            $day->toDateString().' '.$this->settingsService->reportDeadlineTime(),
            self::TIMEZONE,
        )->utc();
    }

    private function stagedAttachment(?string $ulid, User $actor): ?Attachment
    {
        if ($ulid === null || $ulid === '') {
            return null;
        }

        $attachment = Attachment::query()->where('ulid', $ulid)->lockForUpdate()->firstOrFail();
        $person = $actor->person;

        if ($attachment->getAttribute('attachable_type') !== $person->getMorphClass()
            || (int) $attachment->getAttribute('attachable_id') !== (int) $person->getKey()
            || (int) $attachment->getAttribute('uploaded_by') !== (int) $actor->getKey()) {
            throw ValidationException::withMessages([
                'attachment_ulid' => 'Cette preuve ne peut pas être rattachée à ce rapport.',
            ]);
        }

        return $attachment;
    }

    /** @return array<string, mixed> */
    private function reportPayload(DailyReport $report): array
    {
        $version = $report->currentVersion;

        return [
            'id' => $report->getKey(),
            'state' => $report->state->value,
            'state_label' => $report->state->label(),
            'submitted_at' => $report->submitted_at?->toISOString(),
            'lateness_explanation' => $report->lateness_explanation,
            'version' => $version instanceof DailyReportVersion ? [
                'number' => $version->version_number,
                'planned_task' => $version->planned_task,
                'achieved_result' => $version->achieved_result,
                'evidence_link' => $version->evidence_link,
                'attachment_name' => $version->attachment?->original_name,
                'blocker_present' => $version->blocker_present,
                'blocker_details' => $version->blocker_details,
                'next_action' => $version->next_action,
                'help_requested' => $version->help_requested,
                'help_details' => $version->help_details,
            ] : null,
        ];
    }
}
