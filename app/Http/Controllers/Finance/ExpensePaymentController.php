<?php

namespace App\Http\Controllers\Finance;

use App\Enums\FinancialAccountState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CancelPaidExpenseRequest;
use App\Http\Requests\Finance\PayExpenseRequest;
use App\Models\Finance\Account;
use App\Models\Finance\Contract;
use App\Models\Finance\Expense;
use App\Models\Identity\User;
use App\Models\Work\Project;
use App\Services\Finance\ExpensePaymentService;
use App\Services\Platform\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExpensePaymentController extends Controller
{
    public function __construct(
        private readonly ExpensePaymentService $payments,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        abort_unless($actor->hasRole('finance') && $actor->can('depense.payer'), 403);

        return Inertia::render('Finance/ExpensePayments/Index', [
            ...$this->payments->forManagement(),
            'accounts' => Account::query()->where('state', FinancialAccountState::Active->value)->orderBy('label')->get(['id', 'label']),
            'contracts' => Contract::query()->where('state', 'active')->orderBy('reference')->get(['id', 'reference', 'title']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'idempotencyKey' => (string) Str::ulid(),
            'cancellationIdempotencyKey' => (string) Str::ulid(),
            'attachment' => [
                'attachable_id' => (int) $actor->person_id,
                'allowed_types' => $this->settings->attachmentAllowedTypes(),
                'max_size_bytes' => $this->settings->attachmentMaxSizeBytes(),
            ],
        ]);
    }

    public function pay(PayExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->payments->pay($expense, $request->validated(), $this->actor($request));

        return redirect()->route('expense-payments.index')->with('success', 'La dépense approuvée a été payée.');
    }

    public function cancel(CancelPaidExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $counter = $this->payments->cancel(
            $expense,
            (string) $request->validated('reason'),
            (string) $request->validated('payment_idempotency_key'),
            $this->actor($request),
        );

        return redirect()->route('expense-payments.index')->with('success', "Contre-écriture de dépense #{$counter->getKey()} enregistrée.");
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
