<?php

namespace App\Http\Controllers\Work;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Work\AddWorkLinkRequest;
use App\Http\Requests\Work\AttachWorkItemRequest;
use App\Http\Requests\Work\CommentWorkItemRequest;
use App\Http\Requests\Work\ProjectIndexRequest;
use App\Http\Requests\Work\StoreProjectRequest;
use App\Http\Requests\Work\UpdateProjectMembersRequest;
use App\Http\Requests\Work\UpdateProjectStatusRequest;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Work\Deliverable;
use App\Models\Work\Project;
use App\Models\Work\ProjectMember;
use App\Models\Work\WorkComment;
use App\Models\Work\WorkLink;
use App\Services\Platform\SettingsService;
use App\Services\Work\ProjectService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $service, private readonly SettingsService $settings) {}

    public function index(ProjectIndexRequest $request): Response
    {
        $actor = $this->actor($request);
        $filters = $request->validated();
        $projects = Project::query()->select(['id', 'name', 'client_name', 'manager_id', 'start_date', 'end_date', 'status'])->with(['manager:id,person_id', 'manager.person:id,full_name', 'memberships.user.person', 'deliverables'])->when(isset($filters['status']), fn (Builder $query): Builder => $query->where('status', $filters['status']))->orderBy('start_date')->paginate(20)->withQueryString();

        return Inertia::render('Work/Projects/Index', ['projects' => $projects->through(fn (Project $project): array => $this->serialize($project, false))->toArray(), 'filters' => $filters, 'filtersActive' => count($filters) > 0, 'statuses' => $this->options(ProjectStatus::cases()), 'canCreate' => $actor->can('create', Project::class), 'emptyMessage' => 'Aucun projet actif.', 'attachment' => $this->attachmentConfig($actor)]);
    }

    public function show(Request $request, Project $project): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('view', $project);
        $project->load(['manager.person', 'memberships.user.person', 'statusHistory.actor.person', 'deliverables.owner.person', 'comments.author.person', 'links', 'attachments']);

        return Inertia::render('Work/Projects/Show', ['project' => $this->serialize($project, $actor->can('viewBudget', $project)), 'canManage' => $actor->can('update', $project), 'attachment' => $this->attachmentConfig($actor)]);
    }

    public function budget(Request $request, Project $project): Response
    {
        Gate::authorize('viewBudget', $project);

        return Inertia::render('Work/Projects/Budget', ['project' => ['id' => $project->getKey(), 'name' => $project->name, 'planned_budget_xof' => $project->planned_budget_xof, 'spent_budget_xof' => $project->spent_budget_xof]]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $project = $this->service->create($data, $this->actor($request), $this->attachment($data['attachment_ulid'] ?? null));

        return redirect()->route('projects.show', $project)->with('success', 'Le projet a été créé.');
    }

    public function transition(UpdateProjectStatusRequest $request, Project $project): RedirectResponse
    {
        $this->service->transition($project, $request->status(), (string) $request->validated('reason'), $this->actor($request));

        return back()->with('success', 'Le statut du projet a été mis à jour.');
    }

    public function members(UpdateProjectMembersRequest $request, Project $project): RedirectResponse
    {
        $member = User::query()->findOrFail((int) $request->validated('user_id'));
        if ($request->validated('action') === 'add') {
            $this->service->addMember($project, $member, (string) $request->validated('date'), $this->actor($request));
        } else {
            $this->service->removeMember($project, $member, (string) $request->validated('date'), $this->actor($request));
        }

        return back()->with('success', 'La participation au projet a été mise à jour.');
    }

    public function comment(CommentWorkItemRequest $request, Project $project): RedirectResponse
    {
        $this->service->comment($project, (string) $request->validated('body'), $this->actor($request));

        return back()->with('success', 'Le commentaire a été ajouté.');
    }

    public function link(AddWorkLinkRequest $request, Project $project): RedirectResponse
    {
        $this->service->addLink($project, (string) $request->validated('label'), (string) $request->validated('url'), $this->actor($request));

        return back()->with('success', 'Le lien a été ajouté.');
    }

    public function attach(AttachWorkItemRequest $request, Project $project): RedirectResponse
    {
        $this->service->attach($project, $this->attachment($request->validated('attachment_ulid')) ?? abort(404), $this->actor($request));

        return back()->with('success', 'La pièce jointe a été ajoutée.');
    }

    /** @return array<string, mixed> */
    private function serialize(Project $project, bool $budget): array
    {
        return ['id' => $project->getKey(), 'name' => $project->name, 'client_name' => $project->client_name, 'manager' => $project->manager->person->full_name, 'start_date' => $project->start_date->format('d/m/Y'), 'end_date' => $project->end_date?->format('d/m/Y'), 'status' => $project->status->value, 'status_label' => $project->status->label(), 'members' => $project->memberships->map(fn (ProjectMember $member): array => ['name' => $member->user->person->full_name, 'joined_on' => $member->joined_on->format('d/m/Y'), 'left_on' => $member->left_on?->format('d/m/Y')])->all(), 'budget' => $budget ? ['planned_xof' => $project->planned_budget_xof, 'spent_xof' => $project->spent_budget_xof, 'url' => route('projects.budget', $project)] : null, 'deliverables' => $project->deliverables->map(fn (Deliverable $deliverable): array => ['id' => $deliverable->getKey(), 'title' => $deliverable->title, 'status_label' => $deliverable->status->label(), 'variance_label' => $deliverable->scheduleVarianceLabel()])->all(), 'comments' => $project->relationLoaded('comments') ? $project->comments->map(fn (WorkComment $comment): array => ['id' => $comment->getKey(), 'author' => $comment->author->person->full_name, 'body' => $comment->body])->all() : [], 'links' => $project->relationLoaded('links') ? $project->links->map(fn (WorkLink $link): array => ['id' => $link->getKey(), 'label' => $link->label, 'url' => $link->url])->all() : [], 'attachments' => $project->relationLoaded('attachments') ? $project->attachments->map(fn (Attachment $file): array => ['name' => $file->original_name, 'url' => route('attachments.show', $file)])->all() : []];
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
     * @param  list<ProjectStatus>  $cases
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
