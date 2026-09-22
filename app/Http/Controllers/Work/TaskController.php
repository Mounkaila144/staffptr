<?php

namespace App\Http\Controllers\Work;

use App\Enums\WorkPriority;
use App\Enums\WorkTaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Work\AddWorkLinkRequest;
use App\Http\Requests\Work\AttachWorkItemRequest;
use App\Http\Requests\Work\CommentWorkItemRequest;
use App\Http\Requests\Work\StoreTaskRequest;
use App\Http\Requests\Work\TaskIndexRequest;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use App\Models\Work\Task;
use App\Models\Work\WorkComment;
use App\Models\Work\WorkLink;
use App\Services\Platform\SettingsService;
use App\Services\Work\TaskService;
use App\Services\Work\TodayTaskService;
use App\Support\Work\AssignablePeople;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $service, private readonly TodayTaskService $todayTasks, private readonly SettingsService $settings) {}

    public function index(TaskIndexRequest $request): Response
    {
        $actor = $this->actor($request);
        $filters = $request->validated();
        $tasks = Task::query()->select(['id', 'project_id', 'objective_id', 'parent_id', 'assignee_id', 'created_by', 'title', 'due_date', 'priority', 'status'])->with(['assignee.person', 'project:id,name', 'objective:id,title', 'parent:id,title'])->when(! $actor->hasRole('direction'), fn (Builder $q): Builder => $q->where(fn (Builder $scope): Builder => $scope->where('assignee_id', $actor->getKey())->orWhere('created_by', $actor->getKey())))->when(isset($filters['assignee_id']), fn (Builder $q): Builder => $q->where('assignee_id', $filters['assignee_id']))->when(isset($filters['due_date']), fn (Builder $q): Builder => $q->whereDate('due_date', $filters['due_date']))->when(isset($filters['status']), fn (Builder $q): Builder => $q->where('status', $filters['status']))->when(isset($filters['project_id']), fn (Builder $q): Builder => $q->where('project_id', $filters['project_id']))->orderBy('due_date')->paginate(25)->withQueryString();

        return Inertia::render('Work/Tasks/Index', ['tasks' => $tasks->through(fn (Task $task): array => $this->serialize($task))->toArray(), 'filters' => $filters, 'filtersActive' => count($filters) > 0, 'statuses' => $this->options(WorkTaskStatus::cases()), 'priorities' => $this->options(WorkPriority::cases()), 'canCreate' => $actor->can('create', Task::class), 'assignablePeople' => AssignablePeople::options(), 'projects' => $this->projectOptions($actor), 'objectives' => $this->objectiveOptions($actor), 'parentTasks' => $this->parentTaskOptions($actor), 'attachment' => $this->attachmentConfig($actor)]);
    }

    public function today(Request $request): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('viewAny', Task::class);

        return Inertia::render('Work/Tasks/Today', ['tasks' => $this->todayTasks->forUser($actor)->map(fn (Task $task): array => $this->serialize($task))->all(), 'emptyMessage' => 'Aucune tâche prévue aujourd’hui.']);
    }

    public function show(Request $request, Task $task): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('view', $task);
        $task->load(['assignee.person', 'project', 'objective', 'parent', 'children.assignee.person', 'comments.author.person', 'links', 'attachments']);

        return Inertia::render('Work/Tasks/Show', ['task' => $this->serialize($task), 'canUpdate' => $actor->can('update', $task), 'attachment' => $this->attachmentConfig($actor)]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $this->actor($request);
        $task = $this->service->create($data, $actor, $this->attachment($data['attachment_ulid'] ?? null));
        if (isset($data['link_url'])) {
            $this->service->addLink($task, (string) ($data['link_label'] ?? 'Lien'), (string) $data['link_url'], $actor);
        }

        return redirect()->route('tasks.show', $task)->with('success', 'La tâche a été créée.');
    }

    public function comment(CommentWorkItemRequest $request, Task $task): RedirectResponse
    {
        $this->service->comment($task, (string) $request->validated('body'), $this->actor($request));

        return back()->with('success', 'Le commentaire a été ajouté.');
    }

    public function link(AddWorkLinkRequest $request, Task $task): RedirectResponse
    {
        $this->service->addLink($task, (string) $request->validated('label'), (string) $request->validated('url'), $this->actor($request));

        return back()->with('success', 'Le lien a été ajouté.');
    }

    public function attach(AttachWorkItemRequest $request, Task $task): RedirectResponse
    {
        $this->service->attach($task, $this->attachment($request->validated('attachment_ulid')) ?? abort(404), $this->actor($request));

        return back()->with('success', 'La pièce jointe a été ajoutée.');
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

    /** @return list<array{id: int, name: string}> */
    private function objectiveOptions(User $actor): array
    {
        return Objective::query()->visibleTo($actor)->select(['id', 'title', 'user_id'])->orderBy('title')->get()
            ->map(static fn (Objective $objective): array => ['id' => (int) $objective->getKey(), 'name' => $objective->title])
            ->all();
    }

    /**
     * Tâches pouvant servir de parente.
     *
     * Seules les tâches de premier niveau sont proposées : le service refuse une sous-tâche de
     * sous-tâche, et offrir un choix qu'il rejetterait ensuite serait un piège.
     *
     * @return list<array{id: int, name: string}>
     */
    private function parentTaskOptions(User $actor): array
    {
        return Task::query()
            ->whereNull('parent_id')
            ->when(! $actor->hasRole('direction'), fn (Builder $query): Builder => $query->where(
                fn (Builder $scope): Builder => $scope->where('assignee_id', $actor->getKey())->orWhere('created_by', $actor->getKey()),
            ))
            ->select(['id', 'title'])->orderBy('title')->get()
            ->map(static fn (Task $task): array => ['id' => (int) $task->getKey(), 'name' => $task->title])
            ->all();
    }

    /** @return array<string, mixed> */
    private function serialize(Task $task): array
    {
        return ['id' => $task->getKey(), 'title' => $task->title, 'assignee' => $task->relationLoaded('assignee') ? $task->assignee->person->full_name : null, 'due_date' => $task->due_date->format('d/m/Y'), 'priority' => $task->priority->value, 'priority_label' => $task->priority->label(), 'status' => $task->status->value, 'status_label' => $task->status->label(), 'project' => $task->project?->name, 'objective' => $task->objective?->title, 'parent' => $task->parent?->title, 'comments' => $task->relationLoaded('comments') ? $task->comments->map(fn (WorkComment $comment): array => ['id' => $comment->getKey(), 'author' => $comment->author->person->full_name, 'body' => $comment->body])->all() : [], 'links' => $task->relationLoaded('links') ? $task->links->map(fn (WorkLink $link): array => ['id' => $link->getKey(), 'label' => $link->label, 'url' => $link->url])->all() : [], 'attachments' => $task->relationLoaded('attachments') ? $task->attachments->map(fn (Attachment $file): array => ['name' => $file->original_name, 'url' => route('attachments.show', $file)])->all() : []];
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

    /**
     * @param  list<WorkPriority|WorkTaskStatus>  $cases
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
