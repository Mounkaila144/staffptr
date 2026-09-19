<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreMonthlyBudgetRequest;
use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\MonthlyBudget;
use App\Models\Identity\User;
use App\Services\Finance\MonthlyBudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MonthlyBudgetController extends Controller
{
    public function __construct(private readonly MonthlyBudgetService $budgets) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MonthlyBudget::class);
        $month = (string) $request->query('month', now('Africa/Niamey')->format('Y-m'));
        abort_unless(preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $month) === 1, 422);

        return Inertia::render('Finance/Budgets/Index', [
            'comparison' => $this->budgets->comparison($month, $this->actor($request)),
            'categories' => ExpenseCategory::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreMonthlyBudgetRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $this->budgets->save((int) $request->validated('category_id'), (string) $request->validated('month'), (int) $request->validated('budget_amount'), $actor);

        return redirect()->route('monthly-budgets.index', ['month' => $request->validated('month')])->with('success', 'Le budget mensuel a été enregistré.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
