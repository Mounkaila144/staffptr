<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Services\Accountability\AccountabilityDashboardService;
use App\Services\Accountability\InternshipEvaluationService;
use App\Services\Finance\ExpenseApprovalQueueService;
use App\Services\Platform\NotificationReadService;
use App\Services\Work\WorkDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly AccountabilityDashboardService $accountabilityDashboard,
        private readonly ExpenseApprovalQueueService $approvalQueue,
        private readonly NotificationReadService $notificationReadService,
        private readonly WorkDashboardService $workDashboard,
        private readonly InternshipEvaluationService $internshipEvaluations,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->notificationReadService->markForLink($user, route('home', absolute: false));

        return Inertia::render('Identity/Home', [
            'dailyReport' => $this->accountabilityDashboard->dailyReportBlock($user),
            'blockerBlock' => $this->accountabilityDashboard->openBlockers($user),
            'approvalQueue' => $this->approvalQueue->homeBlock($user),
            'workBlocks' => $this->workDashboard->blocks($user),
            // Bloc « Dernière évaluation » : présent pour les seuls comptes en stage (AC 33).
            'lastEvaluation' => $this->internshipEvaluations->lastEvaluationBlock($user),
        ]);
    }
}
