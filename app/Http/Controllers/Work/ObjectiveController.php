<?php

namespace App\Http\Controllers\Work;

use App\Enums\ObjectiveState;
use App\Enums\WorkPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Work\CommentObjectiveRequest;
use App\Http\Requests\Work\CopyObjectiveRequest;
use App\Http\Requests\Work\ObjectiveIndexRequest;
use App\Http\Requests\Work\StoreObjectiveRequest;
use App\Http\Requests\Work\TransitionObjectiveRequest;
use App\Http\Requests\Work\UpdateObjectiveRequest;
use App\Http\Requests\Work\ValidateObjectiveRequest;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Work\Objective;
use App\Services\Platform\SettingsService;
use App\Services\Work\ObjectiveService;
use App\Support\Work\AssignableOwners;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ObjectiveController extends Controller
{
    public function __construct(private readonly ObjectiveService $service, private readonly SettingsService $settings) {}

    public function index(ObjectiveIndexRequest $request): Response
    {
        return $this->renderView($request, 'Work/Objectives/Index');
    }

    public function calendar(ObjectiveIndexRequest $request): Response
    {
        return $this->renderView($request, 'Work/Objectives/Calendar');
    }

    public function summary(ObjectiveIndexRequest $request): Response
    {
        $actor = $this->actor($request);
        $month = $this->month($request->validated('month'));
        $query = Objective::query()->visibleTo($actor)->dueInMonth($month);
        $counts = (clone $query)->selectRaw('state, COUNT(*) as aggregate')->groupBy('state')->pluck('aggregate', 'state')->map(fn ($value): int => (int) $value)->all();
        $members = User::query()->select(['id', 'person_id'])->with('person:id,full_name')->where('state', 'actif')->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('name', ['direction', 'finance', 'tuteur', 'employe', 'stagiaire']))->whereDoesntHave('objectives', fn (Builder $objectives): Builder => $objectives->whereDate('due_date', '>=', $month->startOfMonth()->toDateString())->whereDate('due_date', '<=', $month->endOfMonth()->toDateString())->whereIn('state', array_map(static fn (ObjectiveState $state): string => $state->value, array_filter(ObjectiveState::cases(), static fn (ObjectiveState $state): bool => $state->countsTowardMonthlyLimit()))))->get();
        if (! $actor->hasRole('direction')) {
            $visibleIds = Objective::query()->visibleTo($actor)->select('user_id');
            $members = $members->whereIn('id', $visibleIds->pluck('user_id')->push($actor->getKey())->unique());
        }

        return Inertia::render('Work/Objectives/Summary', ['month' => $month->format('Y-m'), 'counts' => $counts, 'states' => $this->options(ObjectiveState::cases()), 'membersWithoutValidatedObjective' => $members->map(fn (User $user): array => ['id' => $user->getKey(), 'name' => $user->person->full_name])->values()->all()]);
    }

    public function show(Request $request, Objective $objective): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('view', $objective);
        $objective->load(['owner.person', 'companyPriority', 'project', 'attachments', 'comments.author.person', 'versions.author.person']);

        return Inertia::render('Work/Objectives/Show', ['objective' => $this->serialize($objective), 'canUpdate' => $actor->can('update', $objective), 'canValidate' => $actor->can('validate', $objective), 'attachment' => $this->attachmentConfig($actor)]);
    }

    public function store(StoreObjectiveRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $attachment = $this->attachment($data['attachment_ulid'] ?? null);
        $objective = $this->service->propose($data, $this->actor($request), $attachment);

        return redirect()->route('objectives.show', $objective)->with('success', 'L’objectif a été proposé en brouillon.');
    }

    public function update(UpdateObjectiveRequest $request, Objective $objective): RedirectResponse
    {
        $data = $request->validated();
        $this->service->update($objective, $data, $data['reason'] ?? null, $this->actor($request));

        return back()->with('success', 'L’objectif a été mis à jour.');
    }

    public function validateObjective(ValidateObjectiveRequest $request, Objective $objective): RedirectResponse
    {
        $this->service->validate($objective, $this->actor($request));

        return back()->with('success', 'L’objectif a été validé.');
    }

    public function transition(TransitionObjectiveRequest $request, Objective $objective): RedirectResponse
    {
        $this->service->transition($objective, $request->state(), $this->actor($request), $this->attachment($request->validated('attachment_ulid')));

        return back()->with('success', 'L’état de l’objectif a été mis à jour.');
    }

    public function copy(CopyObjectiveRequest $request, Objective $objective): RedirectResponse
    {
        $copy = $this->service->copyToNextMonth($objective, $this->actor($request));

        return redirect()->route('objectives.show', $copy)->with('success', 'Une copie brouillon a été créée pour le mois suivant.');
    }

    public function comment(CommentObjectiveRequest $request, Objective $objective): RedirectResponse
    {
        $this->service->comment($objective, (string) $request->validated('body'), (bool) $request->boolean('correction_requested'), $this->actor($request));

        return back()->with('success', 'Le commentaire a été ajouté.');
    }

    private function renderView(ObjectiveIndexRequest $request, string $component): Response
    {
        $actor = $this->actor($request);
        $month = $this->month($request->validated('month'));
        $filters = $request->validated();
        $query = Objective::query()->select(['id', 'user_id', 'company_priority_id', 'project_id', 'title', 'due_date', 'priority', 'state', 'progress'])->with(['owner:id,person_id', 'owner.person:id,full_name', 'project:id,name'])->visibleTo($actor)->dueInMonth($month)->when(isset($filters['state']), fn (Builder $q): Builder => $q->where('state', $filters['state']))->when(isset($filters['user_id']), fn (Builder $q): Builder => $q->where('user_id', $filters['user_id']))->orderBy('due_date')->paginate(20)->withQueryString();

        return Inertia::render($component, ['objectives' => $query->through(fn (Objective $o): array => $this->serialize($o))->toArray(), 'month' => $month->format('Y-m'), 'filters' => $filters, 'filtersActive' => count($filters) > 0, 'states' => $this->options(ObjectiveState::cases()), 'priorities' => $this->options(WorkPriority::cases()), 'canCreate' => $actor->can('create', Objective::class), 'assignableOwners' => AssignableOwners::optionsFor($actor), 'attachment' => $this->attachmentConfig($actor)]);
    }

    /** @return array<string, mixed> */
    private function serialize(Objective $o): array
    {
        return ['id' => $o->getKey(), 'title' => $o->title, 'description' => $o->description, 'indicator' => $o->indicator, 'target_value' => $o->target_value, 'expected_evidence' => $o->expected_evidence, 'required_means' => $o->required_means, 'owner' => $o->relationLoaded('owner') ? $o->owner->person->full_name : null, 'due_date' => $o->due_date->format('Y-m-d'), 'state' => $o->state->value, 'state_label' => $o->state->label(), 'tone' => $o->state->tone(), 'priority' => $o->priority->value, 'priority_label' => $o->priority->label(), 'progress' => $o->progress, 'project' => $o->project?->name, 'version_number' => $o->version_number, 'attachments' => $o->relationLoaded('attachments') ? $o->attachments->map(fn (Attachment $a): array => ['name' => $a->original_name, 'url' => route('attachments.show', $a)])->all() : []];
    }

    private function attachment(mixed $ulid): ?Attachment
    {
        return is_string($ulid) && $ulid !== '' ? Attachment::query()->where('ulid', $ulid)->firstOrFail() : null;
    }

    /** @return array{attachable_id: int, allowed_types: list<string>, max_size_bytes: int} */
    private function attachmentConfig(User $actor): array
    {
        return ['attachable_id' => $actor->person_id, 'allowed_types' => $this->settings->attachmentAllowedTypes(), 'max_size_bytes' => $this->settings->attachmentMaxSizeBytes()];
    }

    private function month(mixed $value): CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::createFromFormat('!Y-m', $value, 'Africa/Niamey') : CarbonImmutable::now('Africa/Niamey')->startOfMonth();
    }

    /**
     * @param  list<ObjectiveState|WorkPriority>  $cases
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
