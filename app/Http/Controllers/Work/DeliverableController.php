<?php

namespace App\Http\Controllers\Work;

use App\Enums\DeliverableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Work\StoreDeliverableRequest;
use App\Http\Requests\Work\UpdateDeliverableStatusRequest;
use App\Models\Identity\User;
use App\Models\Work\Deliverable;
use App\Models\Work\Project;
use App\Services\Work\DeliverableService;
use App\Support\Work\AssignablePeople;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeliverableController extends Controller
{
    public function __construct(private readonly DeliverableService $service) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Deliverable::class);
        $items = Deliverable::query()->select(['id', 'project_id', 'owner_id', 'title', 'planned_date', 'actual_date', 'status'])->with(['project:id,name,manager_id', 'owner.person'])->orderBy('planned_date')->paginate(25);

        return Inertia::render('Work/Deliverables/Index', ['deliverables' => $items->through(fn (Deliverable $d): array => $this->serialize($d))->toArray(), 'statuses' => $this->options(DeliverableStatus::cases()), 'assignablePeople' => AssignablePeople::options(), 'projects' => $this->projectOptions($this->actor($request))]);
    }

    public function store(StoreDeliverableRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $this->actor($request));

        return back()->with('success', 'Le livrable a été créé.');
    }

    public function transition(UpdateDeliverableStatusRequest $request, Deliverable $deliverable): RedirectResponse
    {
        $this->service->transition($deliverable, $request->status(), (string) $request->validated('reason'), $this->actor($request));

        return back()->with('success', 'Le statut du livrable a été mis à jour.');
    }

    /**
     * Projets rattachables, dans le périmètre de lecture de l'acteur.
     *
     * @return list<array{id: int, name: string}>
     */
    private function projectOptions(User $actor): array
    {
        return Project::query()->visibleTo($actor)->select(['id', 'name'])->orderBy('name')->get()
            ->map(static fn (Project $project): array => ['id' => (int) $project->getKey(), 'name' => $project->name])
            ->all();
    }

    /** @return array<string, mixed> */
    private function serialize(Deliverable $d): array
    {
        return ['id' => $d->getKey(), 'title' => $d->title, 'project' => $d->project->name, 'owner' => $d->owner->person->full_name, 'planned_date' => $d->planned_date->format('d/m/Y'), 'actual_date' => $d->actual_date?->format('d/m/Y'), 'status' => $d->status->value, 'status_label' => $d->status->label(), 'variance_label' => $d->scheduleVarianceLabel()];
    }

    /**
     * @param  list<DeliverableStatus>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(static fn ($case): array => ['value' => $case->value, 'label' => $case->label()], $cases);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
