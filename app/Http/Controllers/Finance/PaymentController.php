<?php

namespace App\Http\Controllers\Finance;

use App\Enums\FinancialAccountState;
use App\Enums\InvoiceState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CancelPaymentRequest;
use App\Http\Requests\Finance\CorrectPaymentRequest;
use App\Http\Requests\Finance\StorePaymentRequest;
use App\Models\Finance\Account;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Models\Work\Project;
use App\Services\Finance\PaymentService;
use App\Services\Platform\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);
        $actor = $this->actor($request);

        return Inertia::render('Finance/Payments/Index', [
            'payments' => $this->payments->forManagement(),
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'contracts' => Contract::query()->where('state', 'active')->orderBy('reference')->get(['id', 'client_id', 'project_id', 'reference', 'title']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'invoices' => Invoice::query()->whereNot('state', InvoiceState::Annulee->value)->orderBy('number')->get(['id', 'client_id', 'contract_id', 'number', 'total_amount']),
            'accounts' => Account::query()->where('state', FinancialAccountState::Active->value)->orderBy('label')->get(['id', 'label']),
            'idempotencyKey' => (string) Str::ulid(),
            'cancellationIdempotencyKey' => (string) Str::ulid(),
            'attachment' => [
                'attachable_id' => (int) $actor->person_id,
                'allowed_types' => $this->settings->attachmentAllowedTypes(),
                'max_size_bytes' => $this->settings->attachmentMaxSizeBytes(),
            ],
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $payment = $this->payments->record($request->validated(), $this->actor($request));

        return redirect()->route('payments.index')->with('success', "Reçu {$payment->receipt_number} enregistré.");
    }

    public function correct(CorrectPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $corrected = $this->payments->correct($payment, $request->validated(), $this->actor($request));

        return redirect()->route('payments.index')->with('success', "Correction enregistrée sous le reçu {$corrected->receipt_number}.");
    }

    public function cancel(CancelPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $reversal = $this->payments->cancel(
            $payment,
            (string) $request->validated('reason'),
            (string) $request->validated('idempotency_key'),
            $this->actor($request),
        );

        return redirect()->route('payments.index')->with('success', "Contre-écriture {$reversal->receipt_number} enregistrée.");
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
