<?php

namespace App\Services\Identity;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HierarchyService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly UserHistoryService $userHistoryService,
    ) {}

    /**
     * @return array{manager: User|null, subordinates: Collection<int, User>}
     */
    public function directRelations(User $user): array
    {
        return [
            'manager' => $user->manager()
                ->where('state', UserState::Actif)
                ->with(['person', 'department', 'jobFunction'])
                ->first(),
            'subordinates' => $user->subordinates()
                ->where('state', UserState::Actif)
                ->with(['person', 'department', 'jobFunction'])
                ->orderBy('id')
                ->get(),
        ];
    }

    public function assertManagerAssignmentAllowed(User $user, ?int $managerId): void
    {
        if ($managerId === null) {
            return;
        }

        if ($managerId === $user->getKey()) {
            $this->throwCycleValidation();
        }

        $candidate = User::query()->select(['id', 'manager_id'])->find($managerId);

        if (! $candidate instanceof User) {
            throw ValidationException::withMessages([
                'manager_id' => "Le responsable choisi n'existe plus.",
            ]);
        }

        $visited = [];
        $current = $candidate;

        while ($current instanceof User) {
            $currentId = (int) $current->getKey();

            if ($currentId === $user->getKey()) {
                $this->throwCycleValidation();
            }

            if (isset($visited[$currentId]) || $current->manager_id === null) {
                return;
            }

            $visited[$currentId] = true;
            $current = User::query()
                ->select(['id', 'manager_id'])
                ->find((int) $current->manager_id);
        }
    }

    /**
     * Change le responsable direct d'un compte, sous verrou, avec contrôle de cycle, audit
     * (`manager_changed`) et historique de fiche.
     *
     * Point d'entrée unique pour les autres modules (ex. `Accountability` quand le tuteur d'un
     * stage devient le responsable du stagiaire — décision du propriétaire du 2026-11-01) : ils
     * ne modifient jamais `users.manager_id` directement (source-tree.md, règle de couplage).
     *
     * @return bool vrai si le responsable a effectivement changé
     */
    public function changeManager(
        User $user,
        ?int $managerId,
        ?User $actor,
        string $reason,
        ?string $actorLabel = null,
    ): bool {
        return DB::connection($user->getConnectionName())->transaction(function () use ($user, $managerId, $actor, $reason, $actorLabel): bool {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $oldManagerId = $locked->manager_id === null ? null : (int) $locked->manager_id;

            if ($oldManagerId === $managerId) {
                return false;
            }

            $this->assertManagerAssignmentAllowed($locked, $managerId);

            $locked->manager_id = $managerId;

            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: fn (): bool => $locked->saveOrFail(),
                actorId: $actor?->getKey(),
                actorLabel: $actorLabel ?? ($actor instanceof User
                    ? ($actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}")
                    : 'Système'),
                action: 'manager_changed',
                oldValues: ['manager_id' => $oldManagerId],
                newValues: ['manager_id' => $managerId],
                reason: $reason,
            );

            $this->userHistoryService->record(
                user: $locked,
                field: 'manager_id',
                oldValue: $this->userHistoryService->managerSnapshot($oldManagerId),
                newValue: $this->userHistoryService->managerSnapshot($managerId),
                actor: $actor,
                reason: $reason,
            );

            $user->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    private function throwCycleValidation(): never
    {
        throw ValidationException::withMessages([
            'manager_id' => 'Ce responsable créerait un cycle hiérarchique. Choisissez une personne hors de cette chaîne.',
        ]);
    }
}
