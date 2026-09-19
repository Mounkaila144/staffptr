<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ReviseCorrectionPlanRequest;
use App\Http\Requests\Finance\StoreCorrectionPlanRequest;
use App\Models\Finance\CorrectionPlan;
use App\Models\Identity\User;
use App\Services\Finance\AlertLevelService;
use App\Services\Finance\CorrectionPlanReminderService;
use App\Services\Finance\CorrectionPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan correctif du niveau orange (AC 11, AC 14 à 18).
 *
 * Le plan reste consultable après le retour au vert : `index` n'est jamais conditionné au niveau
 * courant (AC 15).
 */
class CorrectionPlanController extends Controller
{
    public function __construct(
        private readonly CorrectionPlanService $plans,
        private readonly CorrectionPlanReminderService $reminders,
        private readonly AlertLevelService $alertLevelService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CorrectionPlan::class);
        $month = $this->requestedMonth($request);

        return Inertia::render('Finance/CorrectionPlans/Index', [
            'month' => $month->toDateString(),
            'alert' => $this->alertLevelService->assess($month)->toArray(),
            'plans' => $this->plans->historyFor($month)->map(fn (CorrectionPlan $plan): array => $this->present($plan))->all(),
            'dueLabel' => $this->dueLabel($month),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', CorrectionPlan::class);
        $month = $this->requestedMonth($request);

        return Inertia::render('Finance/CorrectionPlans/Create', [
            'month' => $month->toDateString(),
            'alert' => $this->alertLevelService->assess($month)->toArray(),
            'dueLabel' => $this->dueLabel($month),
            'existingPlan' => $this->plans->currentFor($month) !== null,
        ]);
    }

    public function store(StoreCorrectionPlanRequest $request): RedirectResponse
    {
        /** @var array{month: string, finding: string, actions: string, responsibles: string, due_on: string, expected_result: string} $validated */
        $validated = $request->validated();
        $month = $validated['month'];
        unset($validated['month']);

        $this->plans->create($month, $validated, $this->actor($request));

        return redirect()
            ->route('correction-plans.index', ['mois' => CarbonImmutable::parse($month, 'Africa/Niamey')->startOfMonth()->toDateString()])
            ->with('success', 'Le plan correctif est enregistré. Les relances de la direction cessent.');
    }

    public function validatePlan(Request $request, CorrectionPlan $correctionPlan): RedirectResponse
    {
        Gate::authorize('validatePlan', $correctionPlan);
        $this->plans->validate($correctionPlan, $this->actor($request));

        return redirect()
            ->route('correction-plans.index', ['mois' => $correctionPlan->month->toDateString()])
            ->with('success', 'Le plan correctif est validé. Il ne peut plus être modifié ; une révision créera une nouvelle version.');
    }

    public function revise(ReviseCorrectionPlanRequest $request, CorrectionPlan $correctionPlan): RedirectResponse
    {
        /** @var array{finding: string, actions: string, responsibles: string, due_on: string, expected_result: string, revision_reason: string} $validated */
        $validated = $request->validated();
        $this->plans->revise($correctionPlan, $validated, $this->actor($request));

        return redirect()
            ->route('correction-plans.index', ['mois' => $correctionPlan->month->toDateString()])
            ->with('success', 'La révision est enregistrée comme nouvelle version. La version précédente reste consultable.');
    }

    /** @return array<string, mixed> */
    private function present(CorrectionPlan $plan): array
    {
        return [
            'id' => (int) $plan->getKey(),
            'version' => $plan->version,
            'month' => $plan->month->toDateString(),
            'finding' => $plan->finding,
            'actions' => $plan->actions,
            'responsibles' => $plan->responsibles,
            'due_on' => $plan->due_on->toDateString(),
            'expected_result' => $plan->expected_result,
            'state' => $plan->state->value,
            'state_label' => $plan->state->label(),
            'is_frozen' => $plan->state->isFrozen(),
            'revision_reason' => $plan->revision_reason,
            'created_by' => $plan->creator?->person?->full_name,
            'validated_by' => $plan->validator?->person?->full_name,
            'validated_at' => $plan->validated_at?->setTimezone('Africa/Niamey')->format('d/m/Y H:i'),
        ];
    }

    private function requestedMonth(Request $request): CarbonImmutable
    {
        $requested = $request->query('mois');

        if (! is_string($requested) || preg_match('/\A\d{4}-\d{2}(-\d{2})?\z/', $requested) !== 1) {
            return CarbonImmutable::now('Africa/Niamey')->startOfMonth();
        }

        return CarbonImmutable::parse(
            strlen($requested) === 7 ? $requested.'-01' : $requested,
            'Africa/Niamey',
        )->startOfMonth();
    }

    private function dueLabel(CarbonImmutable $month): string
    {
        $dueAt = $this->reminders->dueAt($month->toDateString(), CarbonImmutable::now('Africa/Niamey'));

        return $dueAt->format('d/m/Y à H\hi');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
