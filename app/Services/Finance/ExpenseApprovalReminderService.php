<?php

namespace App\Services\Finance;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Identity\User;
use App\Notifications\ExpenseApprovalReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ExpenseApprovalReminderService
{
    public const TIMEZONE = 'Africa/Niamey';

    public function dispatchDue(?CarbonImmutable $now = null): int
    {
        $now = ($now ?? CarbonImmutable::now('UTC'))->setTimezone(self::TIMEZONE);
        $approvers = User::permission('depense.approuver')
            ->select(['users.id', 'users.person_id', 'users.phone', 'users.state'])
            ->get();
        $sent = 0;

        foreach ([1, 2] as $dayOffset) {
            foreach ($this->expensesCreatedOn($now->subDays($dayOffset)) as $expense) {
                $decidedIds = $expense->approvals->pluck('approver_id')->map(static fn (mixed $id): int => (int) $id);
                $missingApprovers = $approvers->filter(
                    static fn (User $approver): bool => (int) $approver->getKey() !== (int) $expense->requester_id
                        && ! $decidedIds->contains((int) $approver->getKey()),
                );

                foreach ($missingApprovers as $recipient) {
                    if ($this->dispatchOne($expense, $recipient, $dayOffset)) {
                        $sent++;
                    }
                }
            }
        }

        return $sent;
    }

    /** @return Collection<int, Expense> */
    private function expensesCreatedOn(CarbonImmutable $localDay): Collection
    {
        $startUtc = $localDay->startOfDay()->utc();
        $endUtc = $localDay->endOfDay()->utc();

        return Expense::query()
            ->select([
                'id',
                'requester_id',
                'category_id',
                'reason',
                'requested_amount',
                'state',
                'created_at',
                'updated_at',
            ])
            ->where('state', ExpenseState::Demandee->value)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->with([
                'requester:id,person_id',
                'requester.person:id,full_name',
                'category:id,name',
                'approvals:expense_id,approver_id',
            ])
            ->get();
    }

    private function dispatchOne(Expense $expense, User $recipient, int $dayOffset): bool
    {
        $notificationId = ExpenseApprovalReminderNotification::stableId(
            (int) $expense->getKey(),
            (int) $recipient->getKey(),
            $dayOffset,
        );

        $claimed = DB::connection($expense->getConnectionName())->transaction(function () use (
            $expense,
            $recipient,
            $dayOffset,
            $notificationId,
        ): bool {
            $lockedExpense = Expense::query()
                ->with(['requester.person', 'category', 'approvals'])
                ->whereKey($expense->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedExpense->state !== ExpenseState::Demandee
                || (int) $lockedExpense->requester_id === (int) $recipient->getKey()
                || $lockedExpense->approvals->contains(
                    static fn (ExpenseApproval $approval): bool => (int) $approval->approver_id === (int) $recipient->getKey(),
                )
                || ! User::permission('depense.approuver')->whereKey($recipient->getKey())->exists()
                || DatabaseNotification::query()->whereKey($notificationId)->exists()) {
                return false;
            }

            Notification::sendNow(
                $recipient,
                ExpenseApprovalReminderNotification::forDatabase($lockedExpense, $dayOffset, (int) $recipient->getKey()),
                ['database'],
            );

            return true;
        });

        if (! $claimed) {
            return false;
        }

        $recipient->notify(ExpenseApprovalReminderNotification::forWhatsApp(
            $expense,
            $dayOffset,
            (int) $recipient->getKey(),
        ));

        return true;
    }
}
