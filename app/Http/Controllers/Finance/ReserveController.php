<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ApproveReserveUsageRequest;
use App\Http\Requests\Finance\RequestReserveUsageRequest;
use App\Models\Finance\ReserveMovement;
use App\Models\Identity\User;
use App\Services\Finance\ReserveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReserveController extends Controller
{
    public function __construct(private readonly ReserveService $reserve) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ReserveMovement::class);

        return Inertia::render('Finance/Reserve/Index', [
            'reserve' => $this->reserve->summary($this->actor($request)),
            'idempotencyKey' => (string) Str::ulid(),
            'canApprove' => $this->actor($request)->can('reserve.gerer'),
        ]);
    }

    public function requestUsage(RequestReserveUsageRequest $request): RedirectResponse
    {
        $this->reserve->requestUsage(
            (int) $request->validated('movement_amount'), (string) $request->validated('reason'),
            (string) $request->validated('reconstitution_plan'), (string) $request->validated('idempotency_key'), $this->actor($request),
        );

        return redirect()->route('reserve.index')->with('success', 'La demande d’utilisation attend deux approbations distinctes de la direction.');
    }

    public function approve(ApproveReserveUsageRequest $request, ReserveMovement $reserveMovement): RedirectResponse
    {
        $this->reserve->approveUsage($reserveMovement, $this->actor($request));

        return redirect()->route('reserve.index')->with('success', 'L’approbation de réserve a été enregistrée.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
