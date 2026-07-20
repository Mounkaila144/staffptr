<?php

namespace App\Http\Controllers\Identity;

use App\Enums\UserState;
use App\Exceptions\Identity\RoleAssignmentConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\AccountIndexRequest;
use App\Http\Requests\Identity\ArchiveAccountRequest;
use App\Http\Requests\Identity\StoreAccountRequest;
use App\Http\Requests\Identity\SyncAccountRolesRequest;
use App\Models\Identity\User;
use App\Services\Identity\AccountAdministrationService;
use App\Services\Identity\IdentityService;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountAdministrationService $accountAdministrationService,
        private readonly RoleAssignmentService $roleAssignmentService,
        private readonly IdentityService $identityService,
    ) {}

    public function index(AccountIndexRequest $request): Response
    {
        Gate::authorize('viewAny', User::class);
        $actor = $this->actor($request);

        return Inertia::render(
            'Identity/Accounts/Index',
            $this->accountAdministrationService->indexData($actor, $request->validated()),
        );
    }

    public function store(StoreAccountRequest $request): Response
    {
        Gate::authorize('create', User::class);
        $actor = $this->actor($request);

        try {
            $created = $this->accountAdministrationService->create($actor, $request->validated());
        } catch (RoleAssignmentConflict $exception) {
            throw ValidationException::withMessages(['roles' => $exception->getMessage()]);
        }

        return Inertia::render('Identity/Accounts/Index', [
            ...$this->accountAdministrationService->indexData($actor, []),
            'createdAccount' => [
                'person_name' => $created['user']->person()->value('full_name'),
                'phone' => $created['user']->phone,
                'temporary_password' => $created['temporary_password'],
            ],
        ]);
    }

    public function syncRoles(SyncAccountRolesRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $actor = $this->actor($request);

        try {
            $this->roleAssignmentService->syncRoles(
                user: $user,
                roles: $request->validated('roles'),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                reason: (string) $request->validated('reason'),
            );
        } catch (RoleAssignmentConflict $exception) {
            throw ValidationException::withMessages(['roles' => $exception->getMessage()]);
        }

        return redirect()->route('accounts.index');
    }

    public function archive(ArchiveAccountRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $actor = $this->actor($request);
        $this->identityService->changeUserState(
            user: $user,
            state: UserState::Archive,
            actorId: $actor->getKey(),
            actorLabel: $this->actorLabel($actor),
            reason: (string) $request->validated('reason'),
        );

        return redirect()->route('accounts.index');
    }

    private function actor(FormRequest $request): User
    {
        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
