<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RequestSharePaymentRequest;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;
use App\Services\Finance\ShareEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShareEntitlementController extends Controller
{
    public function __construct(private readonly ShareEntitlementService $shares) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ShareEntitlement::class);
        $actor = $this->actor($request);

        return Inertia::render('Finance/Shares/Index', [
            'shares' => $this->shares->forViewer($actor),
            'ownScope' => ! $actor->hasAnyRole(['direction', 'finance']),
        ]);
    }

    public function show(Request $request, ShareEntitlement $shareEntitlement): Response
    {
        Gate::authorize('view', $shareEntitlement);
        $actor = $this->actor($request);
        $share = collect($this->shares->forViewer($actor))->firstWhere('id', $shareEntitlement->getKey());
        abort_if($share === null, 404);

        return Inertia::render('Finance/Shares/Show', ['share' => $share]);
    }

    public function requestPayment(RequestSharePaymentRequest $request, ShareEntitlement $shareEntitlement): RedirectResponse
    {
        $expense = $this->shares->requestPayment($shareEntitlement, $this->actor($request));

        return redirect()->route('expenses.show', $expense)->with('success', 'La demande de versement suit maintenant le circuit ordinaire à deux approbations.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
