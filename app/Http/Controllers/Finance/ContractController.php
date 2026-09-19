<?php

namespace App\Http\Controllers\Finance;

use App\Enums\RelationType;
use App\Enums\UserState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CloseContractRequest;
use App\Http\Requests\Finance\StoreContractRequest;
use App\Http\Requests\Finance\UpdateContractRequest;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Identity\User;
use App\Models\Work\Project;
use App\Services\Finance\ContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function __construct(private readonly ContractService $contractService) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Contract::class);

        return Inertia::render('Finance/Contracts/Index', [
            'contracts' => $this->contractService->forManagement(),
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'contributors' => User::query()->with('person:id,full_name')
                ->where('state', UserState::Actif->value)
                ->whereIn('relation_type', [RelationType::Dirigeant->value, RelationType::Employe->value])
                ->orderBy('id')->get(['id', 'person_id']),
            'executors' => User::query()->with('person:id,full_name')
                ->where('state', UserState::Actif->value)
                ->whereHas('roles', fn ($query) => $query->where('name', 'direction'))
                ->orderBy('id')->get(['id', 'person_id']),
        ]);
    }

    public function store(StoreContractRequest $request): RedirectResponse
    {
        $this->contractService->create($request->validated(), $this->actor($request));

        return redirect()->route('contracts.index')->with('success', 'Le contrat a été créé.');
    }

    public function update(UpdateContractRequest $request, Contract $contract): RedirectResponse
    {
        $this->contractService->update($contract, $request->validated(), $this->actor($request));

        return redirect()->route('contracts.index')->with('success', 'Le contrat a été mis à jour.');
    }

    public function close(CloseContractRequest $request, Contract $contract): RedirectResponse
    {
        $this->contractService->close($contract, (string) $request->validated('closure_reason'), $this->actor($request));

        return redirect()->route('contracts.index')->with('success', 'Le contrat a été clôturé et sa régularisation a été calculée sans écriture automatique.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
