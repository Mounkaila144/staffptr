<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\PreviewFixedChargeRequest;
use App\Http\Requests\Finance\StoreFixedChargeRequest;
use App\Http\Requests\Finance\UpdateFixedChargeRequest;
use App\Models\Finance\FixedCharge;
use App\Models\Identity\User;
use App\Services\Finance\FixedChargeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FixedChargeController extends Controller
{
    public function __construct(private readonly FixedChargeService $fixedChargeService) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FixedCharge::class);

        return Inertia::render('Finance/FixedCharges/Index', [
            'charges' => $this->fixedChargeService->forManagement(),
            'currentBaseAmount' => $this->fixedChargeService->currentBaseAmount(),
            'reserveObjectiveAmount' => $this->fixedChargeService->reserveObjectiveAmount(),
            'preview' => $request->session()->get('fixed_charge_preview'),
        ]);
    }

    public function preview(PreviewFixedChargeRequest $request): RedirectResponse
    {
        $identifier = $request->validated('fixed_charge_id');
        $charge = $identifier === null ? null : FixedCharge::query()->findOrFail((int) $identifier);
        Gate::authorize($charge === null ? 'create' : 'update', $charge ?? FixedCharge::class);
        $label = trim((string) $request->validated('label'));
        $monthlyAmount = (int) $request->validated('monthly_amount');
        $isActive = $request->boolean('is_active');
        $token = Str::random(48);

        return redirect()->route('fixed-charges.index')->with('fixed_charge_preview', [
            'action' => $charge === null ? 'create' : "update:{$charge->getKey()}",
            'fixed_charge_id' => $charge?->getKey(),
            'label' => $label,
            'monthly_amount' => $monthlyAmount,
            'is_active' => $isActive,
            'token' => $token,
            'impact' => $this->fixedChargeService->preview($charge, $monthlyAmount, $isActive),
        ]);
    }

    public function store(StoreFixedChargeRequest $request): RedirectResponse
    {
        $this->fixedChargeService->create(
            (string) $request->validated('label'),
            (int) $request->validated('monthly_amount'),
            $request->boolean('is_active'),
            $this->actor($request),
        );
        $request->session()->forget('fixed_charge_preview');

        return redirect()->route('fixed-charges.index')->with('success', 'La charge fixe a été créée.');
    }

    public function update(UpdateFixedChargeRequest $request, FixedCharge $fixedCharge): RedirectResponse
    {
        $this->fixedChargeService->update(
            $fixedCharge,
            (string) $request->validated('label'),
            (int) $request->validated('monthly_amount'),
            $request->boolean('is_active'),
            $this->actor($request),
        );
        $request->session()->forget('fixed_charge_preview');

        return redirect()->route('fixed-charges.index')->with('success', 'La charge fixe a été mise à jour.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
