<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ControlMonthlyReportRequest;
use App\Http\Requests\Finance\PrepareMonthlyReportRequest;
use App\Http\Requests\Finance\ReopenMonthRequest;
use App\Http\Requests\Finance\ValidateMonthlyReportRequest;
use App\Models\Finance\MonthlyReport;
use App\Models\Identity\User;
use App\Services\Finance\MonthlyReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MonthlyReportController extends Controller
{
    public function __construct(private readonly MonthlyReportService $service) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MonthlyReport::class);
        $actor = $this->actor($request);

        return Inertia::render('Finance/MonthlyReports/Index', [
            'reports' => Inertia::defer(fn (): array => $this->service->listing($actor)),
            'abilities' => [
                'prepare' => $actor->can('create', MonthlyReport::class),
                'control' => $actor->can('rapport_financier.controler'),
                'validate' => $actor->can('rapport_financier.valider'),
            ],
        ]);
    }

    public function store(PrepareMonthlyReportRequest $request): RedirectResponse
    {
        $this->service->prepare((string) $request->validated('month'), $this->actor($request));

        return redirect()->route('financial-reports.index')->with('success', 'Le rapport mensuel a été généré avec ses douze lignes.');
    }

    public function control(ControlMonthlyReportRequest $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        $this->service->control($monthlyReport, $this->actor($request));

        return redirect()->route('financial-reports.index')->with('success', 'Le contrôle du rapport a été enregistré.');
    }

    public function validateReport(ValidateMonthlyReportRequest $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        $this->service->validate($monthlyReport, $this->actor($request));

        return redirect()->route('financial-reports.index')->with('success', 'Le rapport est validé et le mois est clôturé.');
    }

    public function reopen(ReopenMonthRequest $request, MonthlyReport $monthlyReport): RedirectResponse
    {
        $this->service->reopen($monthlyReport, (string) $request->validated('reason'), $this->actor($request));

        return redirect()->route('financial-reports.index')->with('success', 'Le mois a été rouvert ; les écritures suivantes seront marquées.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
