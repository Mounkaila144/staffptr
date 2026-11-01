<?php

namespace App\Http\Controllers\Accountability;

use App\Enums\BlockerState;
use App\Enums\BlockerUrgency;
use App\Enums\ObjectiveState;
use App\Enums\UserState;
use App\Enums\WorkTaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\StoreBlockerRequest;
use App\Http\Requests\Accountability\TransitionBlockerRequest;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Task;
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
                'urgency_label' => $blocker->urgency === BlockerUrgency::Urgente ? 'Urgente' : 'Normale',
                'state' => $blocker->state->value, 'state_label' => $this->stateLabel($blocker->state), 'creator' => $blocker->creator->person->full_name,
                'solicited' => $blocker->solicitedUser->person->full_name, 'reported_on' => $blocker->reported_on->format('d/m/Y'),
                'deadline_impact' => $blocker->deadline_impact, 'attempted_action' => $blocker->attempted_action,
                'acknowledgement_delay_minutes' => $blocker->acknowledgementDelayMinutes(), 'resolution_delay_minutes' => $blocker->resolutionDelayMinutes(),
                'closure_reason' => $blocker->closure_reason, 'can_transition' => $actor->can('transition', $blocker),
            ])->all(),
            'users' => User::query()->where('state', UserState::Actif)->whereKeyNot($actor->getKey())->with('person')->get()->sortBy('person.full_name')->map(fn (User $user): array => ['id' => $user->getKey(), 'name' => $user->person->full_name])->values()->all(),
            'origins' => $this->originsFor($actor),
            'today' => now('Africa/Niamey')->toDateString(),
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

    private function stateLabel(BlockerState $state): string
    {
        return match ($state) {
            BlockerState::Ouvert => 'Ouvert',
            BlockerState::PrisEnCharge => 'Pris en charge',
            BlockerState::Resolu => 'Résolu',
            BlockerState::FermeSansSolution => 'Fermé sans solution',
        };
    }

    /**
     * Ce qui est bloqué se choisit dans une liste : personne ne connaît le numéro interne
     * d'une tâche, d'un objectif ou d'un rapport.
     *
     * @return list<array{type: string, id: int, label: string}>
     */
    private function originsFor(User $actor): array
    {
        $tasks = Task::query()
            ->where('assignee_id', $actor->getKey())
            ->whereIn('status', [WorkTaskStatus::AFaire, WorkTaskStatus::EnCours, WorkTaskStatus::Bloquee])
            ->orderBy('due_date')
            ->limit(50)
            ->get()
            ->map(fn (Task $task): array => ['type' => 'task', 'id' => (int) $task->getKey(), 'label' => "Tâche : {$task->title} (échéance {$task->due_date->format('d/m/Y')})"]);
        $objectives = Objective::query()
            ->where('user_id', $actor->getKey())
            ->whereIn('state', [ObjectiveState::Valide, ObjectiveState::EnCours, ObjectiveState::Bloque])
            ->orderBy('due_date')
            ->limit(50)
            ->get()
            ->map(fn (Objective $objective): array => ['type' => 'objective', 'id' => (int) $objective->getKey(), 'label' => "Objectif : {$objective->title} (échéance {$objective->due_date->format('d/m/Y')})"]);
        $reports = DailyReport::query()
            ->where('author_id', $actor->getKey())
            ->orderByDesc('report_date')
            ->limit(10)
            ->get()
            ->map(fn (DailyReport $report): array => ['type' => 'daily_report', 'id' => (int) $report->getKey(), 'label' => "Rapport du {$report->report_date->format('d/m/Y')}"]);

        return $tasks->concat($objectives)->concat($reports)->values()->all();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
