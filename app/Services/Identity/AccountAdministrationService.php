<?php

namespace App\Services\Identity;

use App\Enums\PersonOperationalStatus;
use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final class AccountAdministrationService
{
    public function __construct(
        private readonly IdentityService $identityService,
        private readonly RoleAssignmentService $roleAssignmentService,
        private readonly TemporaryPasswordGenerator $temporaryPasswordGenerator,
        private readonly ExpenseApprovalReadiness $expenseApprovalReadiness,
    ) {}

    /**
     * @param  array{state?: string|null, role?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function indexData(User $actor, array $filters): array
    {
        $state = $filters['state'] ?? null;
        $role = $filters['role'] ?? null;
        $baseQuery = User::query()->visibleTo($actor);
        $totalAccounts = (clone $baseQuery)->count();
        $onlyActorExists = $totalAccounts === 1
            && (clone $baseQuery)->whereKey($actor->getKey())->exists();

        $accounts = $baseQuery
            ->with(['person', 'roles'])
            ->when($state, static fn ($query, string $value) => $query->where('state', $value))
            ->when($role, static fn ($query, string $value) => $query->role($value))
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $user): array => $this->serializeAccount($user, $actor));
        $readiness = $this->expenseApprovalReadiness->status();

        return [
            'accounts' => $accounts,
            'filters' => ['state' => $state, 'role' => $role],
            'filtersActive' => $state !== null || $role !== null,
            'roles' => $this->roleOptions(),
            'states' => $this->stateOptions(),
            'people' => Person::query()
                ->visibleTo($actor)
                ->withCount('users')
                ->orderBy('full_name')
                ->get()
                ->map(static fn (Person $person): array => [
                    'id' => (int) $person->getKey(),
                    'name' => $person->full_name,
                    'accounts_count' => $person->users_count,
                ])
                ->all(),
            'readiness' => [
                ...$readiness,
                'first_launch' => $onlyActorExists,
                'message' => $readiness['approval_available']
                    ? null
                    : ($onlyActorExists
                        ? ExpenseApprovalReadiness::UNAVAILABLE_MESSAGE
                        : 'Les dépenses ne sont pas encore approuvables : deux comptes direction sont nécessaires.'),
            ],
        ];
    }

    /**
     * @param array{
     *   person_mode: string,
     *   person_id?: int|null,
     *   full_name?: string|null,
     *   first_seen_at?: string|null,
     *   phone: string,
     *   roles: list<string>
     * } $attributes
     * @return array{user: User, temporary_password: string}
     */
    public function create(User $actor, array $attributes): array
    {
        $connectionName = (new User)->getConnectionName();

        return DB::connection($connectionName)->transaction(function () use ($actor, $attributes): array {
            $person = $attributes['person_mode'] === 'existing'
                ? Person::query()
                    ->visibleTo($actor)
                    ->findOrFail($attributes['person_id'] ?? null)
                : $this->identityService->createPerson(
                    attributes: [
                        'full_name' => trim((string) ($attributes['full_name'] ?? '')),
                        'operational_status' => PersonOperationalStatus::Actif,
                        'first_seen_at' => (string) ($attributes['first_seen_at'] ?? CarbonImmutable::now('Africa/Niamey')->toDateString()),
                    ],
                    actorId: $actor->getKey(),
                    actorLabel: $this->actorLabel($actor),
                    reason: 'Création depuis l’administration des comptes.',
                );
            $temporaryPassword = $this->temporaryPasswordGenerator->generate();
            $user = $this->identityService->createUser(
                person: $person,
                attributes: [
                    'phone' => $attributes['phone'],
                    'password' => $temporaryPassword,
                    'state' => UserState::Actif,
                    'must_change_password' => true,
                ],
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                reason: 'Création depuis l’administration des comptes.',
            );
            $this->roleAssignmentService->syncRoles(
                user: $user,
                roles: $attributes['roles'],
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                reason: 'Rôles initiaux du compte.',
            );

            return ['user' => $user, 'temporary_password' => $temporaryPassword];
        });
    }

    /** @return array{id: int, phone: string, state: string, state_label: string, roles: list<string>, person: array{id: int, name: string, operational_status: string}, is_self: bool} */
    private function serializeAccount(User $user, User $actor): array
    {
        return [
            'id' => (int) $user->getKey(),
            'phone' => $user->phone,
            'state' => $user->state->value,
            'state_label' => $this->stateLabel($user->state),
            'roles' => $user->getRoleNames()->sort()->values()->all(),
            'person' => [
                'id' => (int) $user->person->getKey(),
                'name' => $user->person->full_name,
                'operational_status' => $user->person->operational_status->value,
            ],
            'is_self' => $user->is($actor),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function roleOptions(): array
    {
        $roles = config('permission-catalog.roles');

        if (! is_array($roles)) {
            throw new LogicException('Le catalogue RBAC est invalide.');
        }

        $labels = [
            'super_admin' => 'Super administrateur',
            'direction' => 'Direction',
            'finance' => 'Finance',
            'tuteur' => 'Tuteur',
            'employe' => 'Employé',
            'stagiaire' => 'Stagiaire',
        ];

        return collect(array_keys($roles))
            ->map(static fn (string $role): array => [
                'value' => $role,
                'label' => $labels[$role] ?? $role,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function stateOptions(): array
    {
        return collect(UserState::cases())
            ->map(fn (UserState $state): array => [
                'value' => $state->value,
                'label' => $this->stateLabel($state),
            ])
            ->all();
    }

    private function stateLabel(UserState $state): string
    {
        return match ($state) {
            UserState::Invite => 'Invité',
            UserState::Actif => 'Actif',
            UserState::Suspendu => 'Suspendu',
            UserState::Termine => 'Terminé',
            UserState::Archive => 'Archivé',
        };
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
