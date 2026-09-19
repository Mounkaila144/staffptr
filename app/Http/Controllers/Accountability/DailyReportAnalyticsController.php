<?php

namespace App\Http\Controllers\Accountability;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\DailyReportIndexRequest;
use App\Models\Identity\User;
use App\Services\Accountability\DailyReportAnalyticsService;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DailyReportAnalyticsController extends Controller
{
    public function __construct(private readonly DailyReportAnalyticsService $analytics) {}

    public function __invoke(DailyReportIndexRequest $request): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $view = (string) ($request->validated('vue') ?? 'quotidienne');
        $page = (int) ($request->validated('page') ?? 1);
        $anchor = CarbonImmutable::parse((string) ($request->validated('date') ?? now('Africa/Niamey')->toDateString()), 'Africa/Niamey');

        return Inertia::render('Accountability/DailyReports/Index', [
            'analytics' => $this->analytics->view($actor, $view, $anchor, $page),
        ]);
    }
}
