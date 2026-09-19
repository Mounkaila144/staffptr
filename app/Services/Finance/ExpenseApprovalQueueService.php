<?php

namespace App\Services\Finance;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Identity\ExpenseApprovalReadiness;
use App\Services\Platform\AttachmentService;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExpenseApprovalQueueService
{
    public const EMPTY_MESSAGE = "Aucune dépense n'attend votre approbation. Les demandes apparaîtront ici dès qu'un membre en créera.";

    public function __construct(
        private readonly AttachmentService $attachmentService,
        private readonly ExpenseApprovalReadiness $readiness,
        private readonly AlertLevelExpenseNotice $alertLevelNotice,
    ) {}

    /**
     * @return array{count: int, oldest_age_days: int|null, oldest_age_label: string|null, items: list<array<string, mixed>>, empty_message: string, handled_url: string}|null
     */
    public function homeBlock(User $actor): ?array
    {
        if (! $actor->can('depense.approuver')) {
            return null;
        }

        $query = $this->pendingQuery($actor);
        $count = (clone $query)->count();
        $oldest = (clone $query)->oldest('created_at')->first();
        $ageDays = $oldest instanceof Expense ? $this->ageInCivilDays($oldest) : null;
        $items = $query->oldest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Expense $expense): array => $this->summaryItem($expense))
            ->all();

        return [
            'count' => $count,
            'oldest_age_days' => $ageDays,
            'oldest_age_label' => $ageDays !== null ? $this->ageLabel($ageDays) : null,
            'items' => $items,
            'empty_message' => self::EMPTY_MESSAGE,
            'handled_url' => route('expenses.approvals.index', ['vue' => 'traitees'], false),
        ];
    }

    /** @return Collection<int, Expense> */
    public function pending(User $actor): Collection
    {
        return $this->pendingQuery($actor)
            ->oldest('created_at')
            ->get();
    }

    /** @return Collection<int, Expense> */
    public function handled(User $actor): Collection
    {
        return $this->baseQuery()
            ->whereHas('approvals', static function (Builder $query) use ($actor): void {
                $query->where('approver_id', $actor->getKey());
            })
            ->latest('updated_at')
            ->get();
    }

    public function loadForDecision(Expense $expense): Expense
    {
        return $expense->load([
            'requester:id,person_id',
            'requester.person:id,full_name',
            'category:id,name',
            'approvals.approver.person:id,full_name',
            'attachment',
        ]);
    }

    /** @return array<string, mixed> */
    public function decisionItem(Expense $expense, User $actor): array
    {
        $approvedCount = $expense->approvals
            ->where('decision', ExpenseApproval::DECISION_APPROVE)
            ->pluck('approver_id')
            ->unique()
            ->count();
        $isRequester = (int) $expense->requester_id === (int) $actor->getKey();
        $hasDecision = $expense->approvals
            ->contains(static fn (ExpenseApproval $approval): bool => (int) $approval->approver_id === (int) $actor->getKey());
        $attachment = $expense->attachment;

        return [
            'id' => (int) $expense->getKey(),
            'reason' => $expense->reason,
            'formatted_amount' => $expense->formattedAmount(),
            'beneficiary' => $expense->beneficiary,
            'expected_result' => $expense->expected_result,
            'project_or_contract_note' => $expense->project_or_contract_note,
            'state' => $expense->state->value,
            'created_at' => DateTimeFormatter::format($expense->created_at),
            'requester' => [
                'id' => (int) $expense->requester->getKey(),
                'name' => $expense->requester->person->full_name,
            ],
            'category' => $expense->category->name,
            'approval_count' => $approvedCount,
            'approval_progress' => $this->progressLabel($expense, $approvedCount),
            'approvals' => $expense->approvals
                ->sortBy('decided_at')
                ->values()
                ->map(static fn (ExpenseApproval $approval): array => [
                    'approver' => $approval->approver->person->full_name,
                    'decision' => $approval->decision,
                    'comment' => $approval->comment,
                    'decided_at' => $approval->decided_at !== null
                        ? DateTimeFormatter::format($approval->decided_at)
                        : null,
                ])
                ->all(),
            'attachment' => $attachment instanceof Attachment ? [
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'is_image' => $attachment->isImage(),
                'thumbnail_url' => $this->attachmentService->thumbnailUrl($attachment, false),
                'inline_url' => route('expenses.approvals.attachment', $expense, false),
            ] : null,
            'is_requester' => $isRequester,
            'requester_message' => $isRequester ? ExpenseApprovalService::REQUESTER_MESSAGE : null,
            // Avertissement explicite mais non bloquant du niveau rouge (AC 9). Il ne retire
            // jamais `can_decide` : l'approbation reste possible.
            'alert_warning' => $this->alertLevelNotice->forExpense($expense),
            'has_decided' => $hasDecision,
            'can_decide' => $this->readiness->isApprovalAvailable()
                && $expense->state === ExpenseState::Demandee
                && ! $isRequester
                && ! $hasDecision
                && $actor->can('approve', $expense),
            'decision_url' => route('expenses.approvals.show', $expense, false),
        ];
    }

    /** @return Builder<Expense> */
    private function pendingQuery(User $actor): Builder
    {
        return $this->baseQuery()
            ->where('state', ExpenseState::Demandee->value)
            ->where('requester_id', '!=', $actor->getKey())
            ->whereDoesntHave('approvals', static function (Builder $query) use ($actor): void {
                $query->where('approver_id', $actor->getKey());
            });
    }

    /** @return Builder<Expense> */
    private function baseQuery(): Builder
    {
        return Expense::query()
            ->select([
                'id',
                'requester_id',
                'category_id',
                'reason',
                'requested_amount',
                'beneficiary',
                'expected_result',
                'project_or_contract_note',
                'state',
                'cancel_reason',
                'created_at',
                'updated_at',
            ])
            ->with([
                'requester:id,person_id',
                'requester.person:id,full_name',
                'category:id,name',
                'approvals.approver.person:id,full_name',
                'attachment',
            ]);
    }

    /** @return array<string, mixed> */
    private function summaryItem(Expense $expense): array
    {
        return [
            'id' => (int) $expense->getKey(),
            'reason' => $expense->reason,
            'formatted_amount' => $expense->formattedAmount(),
            'requester' => $expense->requester->person->full_name,
            'age_label' => $this->ageLabel($this->ageInCivilDays($expense)),
            'decision_url' => route('expenses.approvals.show', $expense, false),
        ];
    }

    private function ageInCivilDays(Expense $expense): int
    {
        $timezone = (string) config('app.display_timezone', 'Africa/Niamey');
        $createdDay = CarbonImmutable::instance($expense->created_at)->setTimezone($timezone)->startOfDay();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        return max(0, (int) $createdDay->diffInDays($today));
    }

    private function ageLabel(int $days): string
    {
        return match ($days) {
            0 => "aujourd'hui",
            1 => '1 jour',
            default => "{$days} jours",
        };
    }

    private function progressLabel(Expense $expense, int $approvedCount): string
    {
        return match ($expense->state->value) {
            'approuvee' => 'Approuvée — deux approbations sur deux',
            'refusee' => 'Refusée — une décision de refus suffit',
            'annulee' => 'Annulée',
            default => "Demandée — {$approvedCount} approbation".($approvedCount > 1 ? 's' : '').' sur deux',
        };
    }
}
