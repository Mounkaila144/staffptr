<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ApproveExpenseRequest;
use App\Http\Requests\Finance\ExpenseApprovalIndexRequest;
use App\Http\Requests\Finance\RefuseExpenseRequest;
use App\Models\Finance\Expense;
use App\Models\Identity\User;
use App\Services\Finance\ExpenseApprovalQueueService;
use App\Services\Finance\ExpenseApprovalService;
use App\Services\Identity\ExpenseApprovalReadiness;
use App\Services\Platform\NotificationReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpenseApprovalController extends Controller
{
    public function __construct(
        private readonly ExpenseApprovalService $approvalService,
        private readonly ExpenseApprovalQueueService $approvalQueue,
        private readonly ExpenseApprovalReadiness $readiness,
        private readonly NotificationReadService $notificationReadService,
    ) {}

    public function index(ExpenseApprovalIndexRequest $request): Response
    {
        $actor = $this->actor($request);
        $view = $request->handled() ? 'traitees' : 'en-attente';
        $expenses = ($request->handled()
            ? $this->approvalQueue->handled($actor)
            : $this->approvalQueue->pending($actor))
            ->map(fn (Expense $expense): array => $this->approvalQueue->decisionItem($expense, $actor))
            ->all();

        return Inertia::render('Finance/Expenses/Approvals/Index', [
            'expenses' => $expenses,
            'readiness' => $this->readiness->status(),
            'view' => $view,
            'focusedExpenseId' => null,
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function show(Request $request, Expense $expense): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('viewApproval', $expense);
        $expense = $this->approvalQueue->loadForDecision($expense);
        $this->notificationReadService->markForLink(
            $actor,
            route('expenses.approvals.show', $expense, false),
        );

        return Inertia::render('Finance/Expenses/Approvals/Index', [
            'expenses' => [$this->approvalQueue->decisionItem($expense, $actor)],
            'readiness' => $this->readiness->status(),
            'view' => 'decision',
            'focusedExpenseId' => (int) $expense->getKey(),
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function attachment(Request $request, Expense $expense): BinaryFileResponse
    {
        $this->actor($request);
        Gate::authorize('viewApproval', $expense);
        $attachment = $expense->attachment()->firstOrFail();
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return response()->file($disk->path($attachment->path), [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, max-age=300',
        ])->setContentDisposition('inline', $attachment->original_name);
    }

    public function approve(ApproveExpenseRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('approve', $expense);
        $this->approvalService->approve($expense, $this->actor($request));

        return $this->decisionRedirect($request, $expense)
            ->with('success', 'Votre approbation a été enregistrée.');
    }

    public function refuse(RefuseExpenseRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('refuse', $expense);
        $this->approvalService->refuse(
            $expense,
            $this->actor($request),
            (string) $request->validated('reason'),
        );

        return $this->decisionRedirect($request, $expense)
            ->with('success', 'Votre refus a été enregistré.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function decisionRedirect(Request $request, Expense $expense): RedirectResponse
    {
        return $request->input('return_to') === 'decision'
            ? redirect()->route('expenses.approvals.show', $expense)
            : redirect()->route('expenses.approvals.index');
    }
}
