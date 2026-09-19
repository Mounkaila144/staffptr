<?php

namespace App\Http\Controllers\Accountability;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\CloseImprovementPlanRequest;
use App\Http\Requests\Accountability\StoreImprovementPlanRequest;
use App\Models\Accountability\ImprovementPlan;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use App\Services\Accountability\ImprovementPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ImprovementPlanController extends Controller
{
    public function __construct(private readonly ImprovementPlanService $plans) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('viewAny', ImprovementPlan::class);

        return Inertia::render('Accountability/ImprovementPlans/Index', [
            'plans' => $this->plans->visibleFor($actor)->through(
                fn (ImprovementPlan $plan): array => $this->plans->payload($plan),
            ),
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function show(Request $request, ImprovementPlan $improvementPlan): Response
    {
        Gate::authorize('view', $improvementPlan);
        $actor = $this->actor($request);

        return Inertia::render('Accountability/ImprovementPlans/Show', [
            'plan' => $this->plans->payload($improvementPlan),
            'permissions' => ['close' => $actor->can('close', $improvementPlan)],
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function store(StoreImprovementPlanRequest $request, WeeklyReview $weeklyReview): RedirectResponse
    {
        /** @var list<array{description: string, due_date: string}> $actions */
        $actions = $request->validated('actions');
        $plan = $this->plans->createFromReview($weeklyReview, $this->actor($request), [
            'start_date' => (string) $request->validated('start_date'),
            'end_date' => (string) $request->validated('end_date'),
            'support_provided' => (string) $request->validated('support_provided'),
            'actions' => $actions,
        ]);

        return to_route('improvement-plans.show', $plan)->with('success', 'Accompagnement mis en place.');
    }

    public function close(CloseImprovementPlanRequest $request, ImprovementPlan $improvementPlan): RedirectResponse
    {
        $this->plans->close(
            $improvementPlan,
            $this->actor($request),
            (string) $request->validated('observed_result'),
        );

        return back()->with('success', 'Accompagnement terminé, résultat constaté consigné.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
