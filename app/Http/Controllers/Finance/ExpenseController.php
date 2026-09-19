<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CancelExpenseRequest;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Requests\Finance\UpdateExpenseRequest;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Finance\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenseService) {}

    public function create(Request $request): Response
    {
        $actor = $this->actor($request);

        return Inertia::render('Finance/Expenses/Create', [
            'categories' => ExpenseCategory::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Expense::class);
        $actor = $this->actor($request);

        $query = Expense::query()->visibleTo($actor);

        // Filters
        if ($request->filled('state')) {
            $query->where('state', $request->input('state'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('requester_id')) {
            $query->where('requester_id', $request->input('requester_id'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->input('start_date'),
                $request->input('end_date'),
            ]);
        }

        return Inertia::render('Finance/Expenses/Index', [
            'expenses' => $query
                ->with(['requester.person', 'category'])
                ->orderByDesc('created_at')
                ->get()
                ->map(static fn (Expense $expense): array => [
                    'id' => $expense->getKey(),
                    'reason' => $expense->reason,
                    'requested_amount' => $expense->requested_amount,
                    'formatted_amount' => $expense->formattedAmount(),
                    'beneficiary' => $expense->beneficiary,
                    'state' => $expense->state->value,
                    'created_at' => $expense->created_at->format('d/m/Y H:i'),
                    'requester' => [
                        'id' => $expense->requester->getKey(),
                        'name' => $expense->requester->person->full_name,
                    ],
                    'category' => [
                        'id' => $expense->category->getKey(),
                        'name' => $expense->category->name,
                    ],
                    'cancel_reason' => $expense->cancel_reason,
                ]),

            'categories' => ExpenseCategory::active()
                ->orderBy('name')
                ->get(['id', 'name']),

            'filters' => [
                'state' => $request->input('state'),
                'category_id' => $request->input('category_id'),
                'requester_id' => $request->input('requester_id'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
            ],

            'can_create' => true, // AC 3: all authenticated users can create
        ]);
    }

    public function show(Request $request, Expense $expense): Response
    {
        Gate::authorize('view', $expense);
        $this->actor($request);

        $expense->load(['requester.person', 'category', 'attachment']);

        return Inertia::render('Finance/Expenses/Show', [
            'expense' => [
                'id' => $expense->getKey(),
                'reason' => $expense->reason,
                'requested_amount' => $expense->requested_amount,
                'formatted_amount' => $expense->formattedAmount(),
                'beneficiary' => $expense->beneficiary,
                'expected_result' => $expense->expected_result,
                'project_or_contract_note' => $expense->project_or_contract_note,
                'state' => $expense->state->value,
                'cancel_reason' => $expense->cancel_reason,
                'created_at' => $expense->created_at->format('d/m/Y H:i'),
                'requester' => [
                    'id' => $expense->requester->getKey(),
                    'name' => $expense->requester->person->full_name,
                ],
                'category' => [
                    'id' => $expense->category->getKey(),
                    'name' => $expense->category->name,
                ],
                'attachment' => $expense->attachment?->ulid,
                'can_update' => Gate::allows('update', $expense),
                'can_cancel' => Gate::allows('cancel', $expense),
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);

        $category = ExpenseCategory::query()
            ->whereKey($request->validated('category_id'))
            ->where('is_active', true)
            ->firstOrFail();

        $attachment = null;
        if ($request->filled('attachment_ulid')) {
            $attachment = Attachment::query()
                ->where('ulid', $request->validated('attachment_ulid'))
                ->firstOrFail();
        }

        $expense = $this->expenseService->create(
            $actor,
            $category,
            (string) $request->validated('reason'),
            (int) $request->validated('requested_amount'),
            (string) $request->validated('beneficiary'),
            (string) $request->validated('expected_result'),
            $request->input('project_or_contract_note'),
            $attachment,
            $actor,
        );

        return redirect()->route('expenses.show', $expense)->with('success', 'La demande de dépense a été enregistrée.');
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('update', $expense);
        $actor = $this->actor($request);

        $category = null;
        if ($request->filled('category_id')) {
            $category = ExpenseCategory::query()
                ->whereKey($request->validated('category_id'))
                ->where('is_active', true)
                ->firstOrFail();
        }

        $attachment = null;
        if ($request->filled('attachment_ulid')) {
            $attachment = Attachment::query()
                ->where('ulid', $request->validated('attachment_ulid'))
                ->firstOrFail();
        }

        $this->expenseService->update(
            $expense,
            $category,
            $request->input('reason'),
            $request->input('requested_amount'),
            $request->input('beneficiary'),
            $request->input('expected_result'),
            $request->input('project_or_contract_note'),
            $attachment,
            $actor,
        );

        return redirect()->route('expenses.show', $expense)->with('success', 'La demande de dépense a été mise à jour.');
    }

    public function cancel(CancelExpenseRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('cancel', $expense);
        $actor = $this->actor($request);

        $this->expenseService->cancel(
            $expense,
            (string) $request->validated('cancel_reason'),
            $actor,
        );

        return redirect()->route('expenses.show', $expense)->with('success', 'La demande de dépense a été annulée.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
