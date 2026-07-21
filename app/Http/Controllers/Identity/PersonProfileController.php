<?php

namespace App\Http\Controllers\Identity;

use App\Enums\RelationType;
use App\Enums\UserState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\UpdatePersonProfileRequest;
use App\Models\Identity\Department;
use App\Models\Identity\JobFunction;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Services\Identity\HierarchyService;
use App\Services\Identity\PersonProfileService;
use App\Services\Identity\UserHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PersonProfileController extends Controller
{
    public function __construct(
        private readonly PersonProfileService $personProfileService,
        private readonly HierarchyService $hierarchyService,
        private readonly UserHistoryService $userHistoryService,
    ) {}

    public function show(Request $request, Person $person): Response
    {
        Gate::authorize('view', $person);
        $actor = $this->actor($request);
        $account = $this->personProfileService->currentAccount($person);
        $account->load(['department', 'jobFunction', 'roles']);
        $hierarchy = $this->hierarchyService->directRelations($account);
        $canEdit = Gate::forUser($actor)->allows('update', $person);

        return Inertia::render('Identity/People/Show', [
            'profile' => [
                'person_id' => (int) $person->getKey(),
                'account_id' => (int) $account->getKey(),
                'full_name' => $person->full_name,
                'phone' => $account->phone,
                'photo_url' => $person->photo_path === null
                    ? null
                    : Storage::disk('public')->url($person->photo_path),
                'roles' => $account->getRoleNames()
                    ->sort()
                    ->map(fn (string $role): array => [
                        'value' => $role,
                        'label' => $this->roleLabel($role),
                    ])
                    ->values()
                    ->all(),
                'state' => $account->state->value,
                'state_label' => $account->state->label(),
                'operational_status' => $person->operational_status->value,
                'operational_status_label' => $person->operational_status->label(),
                'department_id' => $account->department_id,
                'department_name' => $account->department?->name,
                'job_function_id' => $account->job_function_id,
                'job_function_name' => $account->jobFunction?->name,
                'manager_id' => $account->manager_id,
                'relation_type' => $account->relation_type->value,
                'relation_type_label' => $account->relation_type->label(),
                'contract_start_date' => $account->contract_start_date?->toDateString(),
                'contract_end_date' => $account->contract_end_date?->toDateString(),
            ],
            'manager' => $hierarchy['manager'] === null
                ? null
                : $this->summary($hierarchy['manager']),
            'subordinates' => $hierarchy['subordinates']
                ->map(fn (User $user): array => $this->summary($user))
                ->all(),
            'canEdit' => $canEdit,
            'departments' => $canEdit ? Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']) : [],
            'jobFunctions' => $canEdit ? JobFunction::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']) : [],
            'managerOptions' => $canEdit ? User::query()
                ->where('state', UserState::Actif)
                ->whereKeyNot($account->getKey())
                ->with('person')
                ->get()
                ->sortBy('person.full_name')
                ->values()
                ->map(fn (User $user): array => $this->summary($user))
                ->all() : [],
            'relationTypes' => $canEdit ? collect(RelationType::cases())
                ->map(fn (RelationType $type): array => ['value' => $type->value, 'label' => $type->label()])
                ->all() : [],
        ]);
    }

    public function update(UpdatePersonProfileRequest $request, Person $person): RedirectResponse
    {
        Gate::authorize('update', $person);
        $account = $this->personProfileService->currentAccount($person);
        $attributes = $request->safe()->except('photo');
        $photo = $request->file('photo');

        if ($photo !== null && $photo->isValid()) {
            // Stockage public transitoire : la story 3.5 pourra migrer ces photos vers le privé.
            $attributes['photo_path'] = $photo->store('people', 'public');
        }

        $this->personProfileService->update($person, $account, $attributes, $this->actor($request));

        return redirect()->route('people.show', $person);
    }

    public function history(Request $request, Person $person): Response
    {
        Gate::authorize('view', $person);
        $this->actor($request);
        $account = $this->personProfileService->currentAccount($person);

        return Inertia::render('Identity/People/History', [
            'person' => [
                'id' => (int) $person->getKey(),
                'name' => $person->full_name,
            ],
            'history' => $this->userHistoryService->forDisplay($account),
        ]);
    }

    /** @return array{id: int, person_id: int, name: string, department: string|null, job_function: string|null} */
    private function summary(User $user): array
    {
        return [
            'id' => (int) $user->getKey(),
            'person_id' => (int) $user->person_id,
            'name' => $user->person->full_name,
            'department' => $user->department?->name,
            'job_function' => $user->jobFunction?->name,
        ];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'direction' => 'Direction',
            'finance' => 'Finance',
            'tuteur' => 'Tuteur',
            'employe' => 'Employé',
            'stagiaire' => 'Stagiaire',
            'super_admin' => 'Super administrateur',
            default => $role,
        };
    }
}
