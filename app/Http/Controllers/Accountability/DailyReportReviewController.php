<?php

namespace App\Http\Controllers\Accountability;

use App\Enums\DailyReportDecisionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\CommentDailyReportRequest;
use App\Http\Requests\Accountability\ReturnDailyReportRequest;
use App\Http\Requests\Accountability\ValidateDailyReportRequest;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Services\Accountability\DailyReportReviewService;
use App\Services\Platform\NotificationReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DailyReportReviewController extends Controller
{
    public function __construct(
        private readonly DailyReportReviewService $reviews,
        private readonly NotificationReadService $notificationReadService,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);

        return Inertia::render('Accountability/DailyReports/Reviews/Index', [
            'reports' => $this->reviews->pending($actor)->map(fn (DailyReport $report): array => $this->reviews->item($report))->all(),
            'focusedReportId' => null,
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function show(Request $request, DailyReport $dailyReport): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('review', $dailyReport);
        $this->notificationReadService->markForLink($actor, route('daily-report-reviews.show', $dailyReport, false));

        return Inertia::render('Accountability/DailyReports/Reviews/Index', [
            'reports' => [$this->reviews->item($dailyReport)],
            'focusedReportId' => (int) $dailyReport->getKey(),
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function comment(CommentDailyReportRequest $request, DailyReport $dailyReport): RedirectResponse
    {
        $this->reviews->comment($dailyReport, $this->actor($request), (string) $request->validated('body'));

        return back()->with('success', 'Commentaire ajouté.');
    }

    public function validateReport(ValidateDailyReportRequest $request, DailyReport $dailyReport): RedirectResponse
    {
        $this->reviews->decide($dailyReport, $this->actor($request), DailyReportDecisionType::Valider);

        return to_route('daily-report-reviews.index')->with('success', 'Rapport validé.');
    }

    public function returnReport(ReturnDailyReportRequest $request, DailyReport $dailyReport): RedirectResponse
    {
        $this->reviews->decide($dailyReport, $this->actor($request), DailyReportDecisionType::Retourner, (string) $request->validated('reason'));

        return to_route('daily-report-reviews.index')->with('success', 'Rapport retourné à son auteur.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
