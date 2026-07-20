<?php

namespace App\Services\Identity;

use App\Enums\PersonOperationalStatus;
use App\Enums\UserState;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

class FirstAdminBootstrapService
{
    public const ACTOR_LABEL = 'Amorçage système';

    public function __construct(
        private readonly IdentityService $identityService,
        private readonly RoleAssignmentService $roleAssignmentService,
    ) {}

    /** @return array{user: User, temporary_password: string} */
    public function create(string $fullName, string $normalizedPhone): array
    {
        $connectionName = (new User)->getConnectionName();

        return DB::connection($connectionName)->transaction(function () use ($fullName, $normalizedPhone): array {
            if (User::query()->exists()) {
                throw new LogicException("L'amorçage est impossible car un compte existe déjà.");
            }

            $temporaryPassword = bin2hex(random_bytes(16));
            $person = $this->identityService->createPerson(
                attributes: [
                    'full_name' => $fullName,
                    'operational_status' => PersonOperationalStatus::Actif,
                    'first_seen_at' => CarbonImmutable::now('Africa/Niamey')->toDateString(),
                ],
                actorId: null,
                actorLabel: self::ACTOR_LABEL,
                reason: 'Création du premier compte de l’installation.',
            );
            $user = $this->identityService->createUser(
                person: $person,
                attributes: [
                    'phone' => $normalizedPhone,
                    'password' => $temporaryPassword,
                    'state' => UserState::Actif,
                    'must_change_password' => true,
                ],
                actorId: null,
                actorLabel: self::ACTOR_LABEL,
                reason: 'Création du premier compte de l’installation.',
            );
            $this->roleAssignmentService->syncRoles(
                user: $user,
                roles: ['super_admin'],
                actorId: null,
                actorLabel: self::ACTOR_LABEL,
                reason: 'Attribution du rôle technique initial.',
            );

            return [
                'user' => $user,
                'temporary_password' => $temporaryPassword,
            ];
        });
    }
}
