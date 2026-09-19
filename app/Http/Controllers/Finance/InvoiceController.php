<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CancelInvoiceRequest;
use App\Http\Requests\Finance\InvoiceIndexRequest;
use App\Http\Requests\Finance\StoreInvoiceRequest;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Invoice;
use App\Models\Identity\User;
use App\Services\Finance\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index(InvoiceIndexRequest $request): Response
    {
        $data = $this->invoiceService->forManagement($request->validated());

        return Inertia::render('Finance/Invoices/Index', [
            ...$data,
            'filters' => $request->validated(),
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'contracts' => Contract::query()->where('state', 'active')->orderBy('reference')->get(['id', 'client_id', 'reference', 'title']),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $this->invoiceService->create($request->validated(), $this->actor($request));

        return redirect()->route('invoices.index')->with('success', 'La facture a été créée.');
    }

    public function cancel(CancelInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoiceService->cancel($invoice, (string) $request->validated('reason'), $this->actor($request));

        return redirect()->route('invoices.index')->with('success', 'La facture a été annulée sans suppression.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
