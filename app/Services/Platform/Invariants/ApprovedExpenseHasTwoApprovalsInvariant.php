<?php

namespace App\Services\Platform\Invariants;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use Illuminate\Database\Eloquent\Builder;
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
                // RM-09 (décision du 2026-11-01) : le nombre exigé est celui figé sur la dépense au
                // passage à « approuvée » (1 ou 2) ; NULL = dépense antérieure, qui en exigeait deux.
                // Une valeur figée hors de 1..2 est elle-même une incohérence.
                ->where(static function (Builder $invalid): void {
                    $invalid->whereRaw(
                        '(SELECT COUNT(DISTINCT approver_id) FROM expense_approvals WHERE expense_approvals.expense_id = expenses.id AND decision = ?) < COALESCE(expenses.approvals_required, 2)',
                        [ExpenseApproval::DECISION_APPROVE],
                    )->orWhere(static function (Builder $outOfRange): void {
                        $outOfRange->whereNotNull('approvals_required')
                            ->whereNotBetween('approvals_required', [1, 2]);
                    });
                })
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        } catch (Throwable) {
            return InvariantResult::fail(
                self::NAME,
                'données de dépenses illisibles',
                'aucune dépense approuvée ou payée avec moins d’approbateurs distincts que le nombre requis',
            );
        }

        $observed = $invalidExpenses === []
            ? 'aucune dépense incohérente'
            : 'dépenses incohérentes : #'.implode(', #', $invalidExpenses);

        return $invalidExpenses === []
            ? InvariantResult::pass(
                self::NAME,
                $observed,
                'aucune dépense approuvée ou payée avec moins d’approbateurs distincts que le nombre requis',
            )
            : InvariantResult::fail(
                self::NAME,
                $observed,
                'aucune dépense approuvée ou payée avec moins d’approbateurs distincts que le nombre requis',
            );
    }
}
