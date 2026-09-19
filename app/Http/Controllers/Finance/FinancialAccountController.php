<?php

namespace App\Http\Controllers\Finance;

use App\Enums\FinancialAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\DeactivateFinancialAccountRequest;
use App\Http\Requests\Finance\StoreFinancialAccountRequest;
use App\Models\Finance\Account;
use App\Models\Identity\User;
use App\Services\Finance\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinancialAccountController extends Controller
{
    public function __construct(private readonly AccountService $accountService) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Account::class);
        $actor = $this->actor($request);

        return Inertia::render('Finance/Accounts/Index', [
            'accounts' => $this->accountService->forManagement($actor),
            'types' => [
                ['value' => FinancialAccountType::Caisse->value, 'label' => 'Caisse'],
                ['value' => FinancialAccountType::Banque->value, 'label' => 'Banque'],
                ['value' => FinancialAccountType::MobileMoney->value, 'label' => 'Mobile Money'],
            ],
        ]);
    }

    public function store(StoreFinancialAccountRequest $request): RedirectResponse
    {
        $attributes = [
            'type' => (string) $request->validated('type'),
            'label' => (string) $request->validated('label'),
            'opening_balance_amount' => (int) $request->validated('opening_balance_amount'),
            'opening_balance_date' => (string) $request->validated('opening_balance_date'),
        ];
        $this->accountService->create($attributes, $this->actor($request));

        return redirect()->route('financial-accounts.index')->with('success', 'Le compte financier a été créé.');
    }

    public function deactivate(DeactivateFinancialAccountRequest $request, Account $account): RedirectResponse
    {
        $this->accountService->deactivate(
            $account,
            (string) $request->validated('reason'),
            $this->actor($request),
        );

        return redirect()->route('financial-accounts.index')->with('success', 'Le compte financier a été désactivé.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
