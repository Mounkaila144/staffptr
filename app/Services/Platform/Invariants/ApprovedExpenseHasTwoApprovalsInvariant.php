<?php

namespace App\Services\Platform\Invariants;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use Throwable;

class ApprovedExpenseHasTwoApprovalsInvariant implements InvariantCheck
{
    private const NAME = 'Double approbation des dépenses approuvées ou payées';

    public function check(): InvariantResult
    {
        try {
            $invalidExpenses = Expense::query()
                ->whereIn('state', [ExpenseState::Approuvee->value, ExpenseState::Payee->value])
                ->whereNull('counter_entry_of_id')
                ->whereRaw(
                    '(SELECT COUNT(DISTINCT approver_id) FROM expense_approvals WHERE expense_approvals.expense_id = expenses.id AND decision = ?) < 2',
                    [ExpenseApproval::DECISION_APPROVE],
                )
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        } catch (Throwable) {
            return InvariantResult::fail(
                self::NAME,
                'données de dépenses illisibles',
                'aucune dépense approuvée ou payée avec moins de 2 approbateurs distincts',
            );
        }

        $observed = $invalidExpenses === []
            ? 'aucune dépense incohérente'
            : 'dépenses incohérentes : #'.implode(', #', $invalidExpenses);

        return $invalidExpenses === []
            ? InvariantResult::pass(
                self::NAME,
                $observed,
                'aucune dépense approuvée ou payée avec moins de 2 approbateurs distincts',
            )
            : InvariantResult::fail(
                self::NAME,
                $observed,
                'aucune dépense approuvée ou payée avec moins de 2 approbateurs distincts',
            );
    }
}
