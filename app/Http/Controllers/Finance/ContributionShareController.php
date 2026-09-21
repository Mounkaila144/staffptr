<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ApproveCapitalContributionRequest;
use App\Http\Requests\Finance\RefuseCapitalContributionRequest;
use App\Http\Requests\Finance\StoreCapitalContributionRequest;
use App\Models\Finance\CapitalContribution;
use App\Models\Finance\ContributionShare;
use App\Models\Identity\User;
use App\Services\Finance\CapitalContributionService;
use App\Services\Finance\ContributionShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContributionShareController extends Controller
{
    public function __construct(
        private readonly ContributionShareService $contributionShares,
        private readonly CapitalContributionService $capitalContributions,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ContributionShare::class);
        $actor = $this->actor($request);

        return Inertia::render('Finance/ContributionShares/Index', [
            'register' => $this->contributionShares->register(),
            'capitalContributions' => $this->capitalContributions->pendingAndSettled($actor),
            'idempotencyKey' => (string) Str::ulid(),
            'canContribute' => $actor->can('create', CapitalContribution::class),
        ]);
    }

    public function storeCapitalContribution(StoreCapitalContributionRequest $request): RedirectResponse
    {
        $this->capitalContributions->record(
            (int) $request->validated('contribution_amount'),
            (string) $request->validated('purpose'),
            (string) $request->validated('idempotency_key'),
            $this->actor($request),
        );

        return redirect()->route('contribution-shares.index')
            ->with('success', 'L’apport attend l’accord du second directeur ; aucune part n’est émise avant.');
    }

    public function approveCapitalContribution(ApproveCapitalContributionRequest $request, CapitalContribution $capitalContribution): RedirectResponse
    {
        $this->capitalContributions->approve($capitalContribution, $this->actor($request));

        return redirect()->route('contribution-shares.index')
            ->with('success', 'L’apport est approuvé et a émis des parts définitives.');
    }

    public function refuseCapitalContribution(RefuseCapitalContributionRequest $request, CapitalContribution $capitalContribution): RedirectResponse
    {
        $this->capitalContributions->refuse(
            $capitalContribution,
            (string) $request->validated('refusal_reason'),
            $this->actor($request),
        );

        return redirect()->route('contribution-shares.index')
            ->with('success', 'Le refus et son motif ont été consignés.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
