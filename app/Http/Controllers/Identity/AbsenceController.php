<?php

namespace App\Http\Controllers\Identity;

use App\Enums\AbsenceState;
use App\Enums\AbsenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\AbsenceIndexRequest;
use App\Http\Requests\Identity\ApproveAbsenceRequest;
use App\Http\Requests\Identity\CancelAbsenceRequest;
use App\Http\Requests\Identity\RefuseAbsenceRequest;
use App\Http\Requests\Identity\StoreAbsenceRequest;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Identity\AbsenceService;
use App\Services\Platform\SettingsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AbsenceController extends Controller
{
    public function __construct(
        private readonly AbsenceService $absenceService,
        private readonly SettingsService $settingsService,
    ) {}

    public function index(AbsenceIndexRequest $request): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('viewAny', Absence::class);
        $relations = ['user.person', 'user.manager.person', 'decidedBy.person', 'attachment'];

        $mine = Absence::query()->with($relations)->where('user_id', $actor->getKey())->latest('start_date')->get();
        $pending = Absence::query()->with($relations)
            ->where('state', AbsenceState::Demandee)
            ->whereHas('user', fn ($query) => $query->where('manager_id', $actor->getKey()))
            ->latest('start_date')
            ->get();
        $visible = Absence::query()->with($relations)->visibleTo($actor)->latest('start_date')->get();

        return Inertia::render('Identity/Absences/Index', [
            'myAbsences' => $this->serializeMany($mine, $actor),
            'pendingApprovals' => $this->serializeMany($pending, $actor),
            'visibleAbsences' => $this->serializeMany($visible, $actor),
            'types' => array_map(
                static fn (AbsenceType $type): array => ['value' => $type->value, 'label' => $type->label()],
                AbsenceType::cases(),
            ),
            'attachment' => [
                'attachable_id' => $actor->person_id,
                'allowed_types' => $this->settingsService->attachmentAllowedTypes(),
                'max_size_bytes' => $this->settingsService->attachmentMaxSizeBytes(),
            ],
        ]);
    }

    public function show(Request $request, Absence $absence): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('view', $absence);
        $absence->load(['user.person', 'user.manager.person', 'decidedBy.person', 'attachment']);

        return Inertia::render('Identity/Absences/Show', [
            'absence' => $this->serialize($absence, $actor),
        ]);
    }

    public function store(StoreAbsenceRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $attachmentUlid = $request->validated('attachment_ulid');
        $attachment = is_string($attachmentUlid)
            ? Attachment::query()->where('ulid', $attachmentUlid)->firstOrFail()
            : null;
        $absence = $this->absenceService->declare(
            $actor,
            $request->type(),
            (string) $request->validated('start_date'),
            (string) $request->validated('end_date'),
            (string) $request->validated('reason'),
            $attachment,
            $actor,
        );

        return redirect()->route('absences.show', $absence)->with('success', "La demande d'absence a été envoyée.");
    }

    public function approve(ApproveAbsenceRequest $request, Absence $absence): RedirectResponse
    {
        $this->absenceService->approve($absence, $this->actor($request));

        return back()->with('success', "L'absence a été approuvée.");
    }

    public function refuse(RefuseAbsenceRequest $request, Absence $absence): RedirectResponse
    {
        $this->absenceService->refuse(
            $absence,
            (string) $request->validated('decision_reason'),
            $this->actor($request),
        );

        return back()->with('success', "L'absence a été refusée.");
    }

    public function cancel(CancelAbsenceRequest $request, Absence $absence): RedirectResponse
    {
        $this->absenceService->cancel($absence, $this->actor($request));

        return back()->with('success', "La demande d'absence a été annulée.");
    }

    /**
     * @param  Collection<int, Absence>  $absences
     * @return list<array<string, mixed>>
     */
    private function serializeMany(Collection $absences, User $actor): array
    {
        return $absences->map(fn (Absence $absence): array => $this->serialize($absence, $actor))->all();
    }

    /** @return array<string, mixed> */
    private function serialize(Absence $absence, User $actor): array
    {
        return [
            'id' => $absence->getKey(),
            'type' => $absence->type->value,
            'type_label' => $absence->type->label(),
            'state' => $absence->state->value,
            'state_label' => $absence->state->label(),
            'start_date' => $absence->start_date->format('Y-m-d'),
            'end_date' => $absence->end_date->format('Y-m-d'),
            'display_period' => $this->displayPeriod($absence),
            'reason' => $absence->reason,
            'decision_reason' => $absence->decision_reason,
            'user' => [
                'id' => $absence->user_id,
                'name' => $absence->user->person->full_name,
            ],
            'manager_name' => $absence->user->manager?->person?->full_name,
            'decided_by_name' => $absence->decidedBy?->person?->full_name,
            'attachment' => $absence->attachment instanceof Attachment ? [
                'name' => $absence->attachment->original_name,
                'url' => route('attachments.show', $absence->attachment),
            ] : null,
            'can_approve' => $actor->can('approve', $absence) && $absence->isPending(),
            'can_refuse' => $actor->can('refuse', $absence) && $absence->isPending(),
            'can_cancel' => $actor->can('cancel', $absence) && $absence->isPending(),
        ];
    }

    private function displayPeriod(Absence $absence): string
    {
        $start = $absence->start_date->locale('fr')->isoFormat('D MMM YYYY');
        $end = $absence->end_date->locale('fr')->isoFormat('D MMM YYYY');

        return $start === $end ? $start : "Du {$start} au {$end}";
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
