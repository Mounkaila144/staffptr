<?php

namespace App\Http\Controllers\Accountability;

use App\Enums\BlockerState;
use App\Enums\BlockerUrgency;
use App\Enums\UserState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\StoreBlockerRequest;
use App\Http\Requests\Accountability\TransitionBlockerRequest;
use App\Models\Accountability\Blocker;
use App\Models\Identity\User;
use App\Services\Accountability\BlockerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlockerController extends Controller
{
    public function __construct(private readonly BlockerService $service) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $blockers = Blocker::query()
            ->visibleTo($actor)
            ->whereIn('state', [BlockerState::Ouvert, BlockerState::PrisEnCharge])
            ->with(['creator.person', 'solicitedUser.person', 'origin'])
            ->latest()
            ->get();

        return Inertia::render('Accountability/Blockers/Index', [
            'blockers' => $blockers->map(fn (Blocker $blocker): array => [
                'id' => $blocker->getKey(), 'problem' => $blocker->problem, 'urgency' => $blocker->urgency->value,
                'state' => $blocker->state->value, 'creator' => $blocker->creator->person->full_name,
                'solicited' => $blocker->solicitedUser->person->full_name, 'reported_on' => $blocker->reported_on->format('d/m/Y'),
                'deadline_impact' => $blocker->deadline_impact, 'attempted_action' => $blocker->attempted_action,
                'acknowledgement_delay_minutes' => $blocker->acknowledgementDelayMinutes(), 'resolution_delay_minutes' => $blocker->resolutionDelayMinutes(),
                'closure_reason' => $blocker->closure_reason, 'can_transition' => $actor->can('transition', $blocker),
            ])->all(),
            'users' => User::query()->where('state', UserState::Actif)->with('person')->get()->sortBy('person.full_name')->map(fn (User $user): array => ['id' => $user->getKey(), 'name' => $user->person->full_name])->values()->all(),
            'urgencies' => array_map(fn (BlockerUrgency $urgency): array => ['value' => $urgency->value, 'label' => $urgency === BlockerUrgency::Urgente ? 'Urgente' : 'Normale'], BlockerUrgency::cases()),
        ]);
    }

    public function store(StoreBlockerRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $this->actor($request));

        return back()->with('success', 'Le blocage a été signalé.');
    }

    public function transition(TransitionBlockerRequest $request, Blocker $blocker): RedirectResponse
    {
        $this->service->transition($blocker, $this->actor($request), BlockerState::from((string) $request->validated('state')), $request->validated('closure_reason'));

        return back()->with('success', 'Le blocage a été mis à jour.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
