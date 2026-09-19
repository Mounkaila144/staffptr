<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ChangeExpenseCategoryActivityRequest;
use App\Http\Requests\Finance\StoreExpenseCategoryRequest;
use App\Http\Requests\Finance\UpdateExpenseCategoryRequest;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Services\Finance\ExpenseCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function __construct(private readonly ExpenseCategoryService $categoryService) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ExpenseCategory::class);
        $this->actor($request);

        return Inertia::render('Finance/ExpenseCategories/Index', [
            'categories' => ExpenseCategory::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(static fn (ExpenseCategory $category): array => [
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'is_essential' => $category->is_essential,
                    'is_active' => $category->is_active,
                ]),
            'activeCategories' => $this->categoryService->activeForSelection()
                ->map(static fn (ExpenseCategory $category): array => [
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'is_essential' => $category->is_essential,
                ]),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create(
            (string) $request->validated('name'),
            $request->boolean('is_essential'),
            $this->actor($request),
        );

        return redirect()->route('expense-categories.index')->with('success', 'La catégorie a été créée.');
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->categoryService->rename($expenseCategory, (string) $request->validated('name'), $actor);
        $this->categoryService->setEssential($expenseCategory, $request->boolean('is_essential'), $actor);

        return redirect()->route('expense-categories.index')->with('success', 'La catégorie a été mise à jour.');
    }

    public function deactivate(ChangeExpenseCategoryActivityRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->categoryService->deactivate($expenseCategory, $this->actor($request));

        return redirect()->route('expense-categories.index')->with('success', 'La catégorie a été désactivée.');
    }

    public function reactivate(ChangeExpenseCategoryActivityRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->categoryService->reactivate($expenseCategory, $this->actor($request));

        return redirect()->route('expense-categories.index')->with('success', 'La catégorie a été réactivée.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
