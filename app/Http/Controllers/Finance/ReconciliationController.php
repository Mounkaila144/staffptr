<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CorrectReconciliationRequest;
use App\Http\Requests\Finance\StoreReconciliationRequest;
use App\Http\Requests\Finance\ValidateReconciliationRequest;
use App\Models\Finance\Account;
use App\Models\Finance\Reconciliation;
use App\Models\Identity\User;
use App\Services\Finance\ReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationService $service) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Reconciliation::class);

        return Inertia::render('Finance/Reconciliations/Index', [
            'reconciliations' => $this->service->listing($this->actor($request)),
            'accounts' => Account::query()->orderBy('label')->get(['id', 'label']),
            'responsibles' => User::query()->with('person:id,full_name')->where('state', 'actif')->orderBy('id')->get(['id', 'person_id']),
        ]);
    }

    public function store(StoreReconciliationRequest $request): RedirectResponse
    {
        $this->service->prepare($request->validated(), $this->actor($request));

        return redirect()->route('reconciliations.index')->with('success', 'Le rapprochement a été préparé ; son écart est affiché.');
    }

    public function validateReconciliation(ValidateReconciliationRequest $request, Reconciliation $reconciliation): RedirectResponse
    {
        $this->service->validate($reconciliation, $this->actor($request));

        return redirect()->route('reconciliations.index')->with('success', 'Le rapprochement a été validé et figé.');
    }

    public function correct(CorrectReconciliationRequest $request, Reconciliation $reconciliation): RedirectResponse
    {
        $this->service->prepare($request->validated(), $this->actor($request), $reconciliation);

        return redirect()->route('reconciliations.index')->with('success', 'La correction a créé un nouveau rapprochement lié.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
