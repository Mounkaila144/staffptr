<?php

namespace App\Services\Accountability;

use App\Enums\DailyReportDecisionType;
use App\Enums\DailyReportState;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportComment;
use App\Models\Accountability\DailyReportDecision;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Notifications\DailyReportDecisionNotification;
use App\Services\Platform\AttachmentService;
use App\Support\Auditing\AuditLogger;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class DailyReportReviewService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AccountabilityDashboardService $dashboardService,
        private readonly AttachmentService $attachmentService,
    ) {}

    /** @return Collection<int, DailyReport> */
    public function pending(User $reviewer): Collection
    {
        return DailyReport::query()
            ->visibleTo($reviewer)
            ->where('author_id', '!=', $reviewer->getKey())
            ->whereIn('state', [DailyReportState::Envoye, DailyReportState::EnRetard])
            ->with(['author.person', 'currentVersion.attachment', 'comments.author.person', 'versions.author.person', 'versions.attachment'])
            ->orderBy('submitted_at')
            ->get();
    }

    public function comment(DailyReport $report, User $reviewer, string $body): DailyReportComment
    {
        Gate::forUser($reviewer)->authorize('review', $report);

        return DB::connection($report->getConnectionName())->transaction(function () use ($report, $reviewer, $body): DailyReportComment {
            $locked = DailyReport::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $comment = new DailyReportComment;
            $comment->fill(['daily_report_id' => $locked->getKey(), 'author_id' => $reviewer->getKey(), 'body' => $body]);
            $comment->saveOrFail();

            return $comment;
        });
    }

    public function decide(DailyReport $report, User $reviewer, DailyReportDecisionType $decision, ?string $reason = null): DailyReport
    {
        Gate::forUser($reviewer)->authorize('review', $report);

        if ($decision === DailyReportDecisionType::Retourner && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Indiquez pourquoi le rapport est retourné.']);
        }

        $decided = DB::connection($report->getConnectionName())->transaction(function () use ($report, $reviewer, $decision, $reason): DailyReport {
            $locked = DailyReport::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($locked->state, [DailyReportState::Envoye, DailyReportState::EnRetard], true)) {
                throw ValidationException::withMessages(['report' => "Ce rapport n'attend plus de décision."]);
            }

            $oldState = $locked->state;
            $target = $decision === DailyReportDecisionType::Valider ? DailyReportState::Valide : DailyReportState::Retourne;
            $record = new DailyReportDecision;
            $record->fill([
                'daily_report_id' => $locked->getKey(),
                'reviewer_id' => $reviewer->getKey(),
                'decision' => $decision,
                'reason' => $reason,
                'decided_at' => CarbonImmutable::now('UTC'),
            ]);
            $record->saveOrFail();
            $locked->state = $target;
            $locked->saveOrFail();
            $this->auditLogger->record(
                actorId: $reviewer->getKey(),
                actorLabel: $reviewer->person()->value('full_name') ?? "Compte #{$reviewer->getKey()}",
                auditable: $locked,
                action: $decision === DailyReportDecisionType::Valider ? 'daily_report_validated' : 'daily_report_returned',
                oldValues: ['state' => $oldState->value],
                newValues: ['state' => $target->value],
                reason: $reason,
            );

            return $locked->load('author');
        });

        $this->dashboardService->invalidate($decided->author, $decided->report_date);
        Notification::sendNow($decided->author, DailyReportDecisionNotification::forDatabase($decided, $decision, $reason), ['database']);
        $decided->author->notify(DailyReportDecisionNotification::forWhatsApp($decided, $decision, $reason));

        return $decided;
    }

    /** @return array<string, mixed> */
    public function item(DailyReport $report): array
    {
        $report->loadMissing(['author.person', 'currentVersion.attachment', 'comments.author.person', 'versions.author.person', 'versions.attachment', 'decisions.reviewer.person']);
        $current = $report->currentVersion;

        return [
            'id' => $report->getKey(),
            'author' => $report->author->person->full_name,
            'date' => $report->report_date->format('d/m/Y'),
            'state' => $report->state->value,
            'state_label' => $report->state->label(),
            'submitted_at' => $report->submitted_at ? DateTimeFormatter::format($report->submitted_at) : null,
            'current' => $current instanceof DailyReportVersion ? $this->versionPayload($current) : null,
            'versions' => $report->versions->sortByDesc('version_number')->map(fn (DailyReportVersion $version): array => $this->versionPayload($version))->values()->all(),
            'comments' => $report->comments->map(fn (DailyReportComment $comment): array => [
                'id' => $comment->getKey(),
                'author' => $comment->author->person->full_name,
                'body' => $comment->body,
                'created_at' => DateTimeFormatter::format($comment->created_at),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function versionPayload(DailyReportVersion $version): array
    {
        $attachment = $version->attachment;

        return [
            'number' => $version->version_number,
            'planned_task' => $version->planned_task,
            'achieved_result' => $version->achieved_result,
            'evidence_link' => $version->evidence_link,
            'attachment_name' => $attachment?->original_name,
            'attachment_url' => $attachment instanceof Attachment ? route('attachments.show', $attachment) : null,
            'thumbnail_url' => $attachment instanceof Attachment ? $this->attachmentService->thumbnailUrl($attachment, false) : null,
            'blocker_present' => $version->blocker_present,
            'blocker_details' => $version->blocker_details,
            'next_action' => $version->next_action,
            'help_requested' => $version->help_requested,
            'help_details' => $version->help_details,
            'correction_reason' => $version->correction_reason,
            'created_at' => DateTimeFormatter::format($version->created_at),
        ];
    }
}
