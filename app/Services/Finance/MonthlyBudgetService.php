<?php

namespace App\Services\Finance;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\MonthlyBudget;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class MonthlyBudgetService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @return array{month: string, month_label: string, empty_message: string|null, rows: list<array<string, mixed>>} */
    public function comparison(string $month, ?User $viewer = null): array
    {
        $start = CarbonImmutable::parse($month, 'Africa/Niamey')->startOfMonth();
        $end = $start->endOfMonth();
        $budgets = MonthlyBudget::query()->when($viewer !== null, fn ($query) => $query->visibleTo($viewer))->with('category')->whereDate('month', $start->toDateString())->get()->keyBy('category_id');
        $actuals = Expense::query()
            ->selectRaw('category_id, SUM(CASE WHEN counter_entry_of_id IS NULL THEN requested_amount ELSE -requested_amount END) AS actual_amount')
            ->where('state', ExpenseState::Payee->value)
            ->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('category_id')->pluck('actual_amount', 'category_id');

        $categoryIds = $budgets->keys()->merge($actuals->keys())->unique()->all();
        $categories = ExpenseCategory::query()->whereKey($categoryIds)->orderBy('name')->get()->keyBy('id');
        $rows = [];
        foreach ($categoryIds as $categoryId) {
            $budget = $budgets->get($categoryId);
            $actual = max(0, (int) ($actuals[$categoryId] ?? 0));
            $budgetAmount = $budget === null ? null : (int) $budget->budget_amount;
            $variance = $budgetAmount === null ? null : $actual - $budgetAmount;
            $percentage = $budgetAmount !== null && $budgetAmount > 0 ? (int) round(($actual * 100) / $budgetAmount) : null;
            $rows[] = [
                'category_id' => (int) $categoryId,
                'category_name' => (string) $categories->get($categoryId)?->name,
                'budget_amount' => $budgetAmount,
                'budget_amount_label' => $budgetAmount === null ? 'Non défini' : Money::from($budgetAmount)->format(),
                'actual_amount' => $actual,
                'actual_amount_label' => Money::from($actual)->format(),
                'variance_amount' => $variance,
                'variance_amount_label' => $variance === null ? 'Sans budget' : ($variance > 0 ? '+' : '').Money::from(abs($variance))->format(),
                'actual_percentage' => $percentage,
                'actual_percentage_label' => $percentage === null ? 'Non calculable' : $percentage.' %',
                'is_exceeded' => $variance !== null && $variance > 0,
                'status_label' => $variance !== null && $variance > 0 ? 'Budget dépassé' : ($budgetAmount === null ? 'Aucun budget — dépense autorisée' : 'Dans le budget'),
            ];
        }

        return [
            'month' => $start->format('Y-m'),
            'month_label' => $start->locale('fr')->translatedFormat('F Y'),
            'empty_message' => $budgets->isEmpty() ? 'Aucun budget défini pour '.$start->locale('fr')->translatedFormat('F').'. Les dépenses restent possibles.' : null,
            'rows' => $rows,
        ];
    }

    public function save(int $categoryId, string $month, int $amount, User $actor): MonthlyBudget
    {
        $monthDate = CarbonImmutable::parse($month, 'Africa/Niamey')->startOfMonth()->toDateString();

        return DB::transaction(function () use ($categoryId, $monthDate, $amount, $actor): MonthlyBudget {
            $budget = MonthlyBudget::query()->where('category_id', $categoryId)->whereDate('month', $monthDate)->lockForUpdate()->first();
            $oldValues = $budget?->getRawOriginal();
            $budget ??= new MonthlyBudget(['category_id' => $categoryId, 'month' => $monthDate, 'created_by' => $actor->getKey()]);
            $budget->budget_amount = $amount;
            $this->auditLogger->runExplicitly(
                auditable: $budget,
                operation: fn (): bool => $budget->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
                action: $budget->exists ? 'monthly_budget_updated' : 'monthly_budget_created',
                oldValues: $oldValues,
                newValues: $budget->getAttributes(),
                reason: 'Budget mensuel par catégorie.',
            );

            return $budget;
        });
    }
}
