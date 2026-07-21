<?php

namespace App\Services\Identity;

use App\Enums\UserState;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class HierarchyService
{
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

    private function throwCycleValidation(): never
    {
        throw ValidationException::withMessages([
            'manager_id' => 'Ce responsable créerait un cycle hiérarchique. Choisissez une personne hors de cette chaîne.',
        ]);
    }
}
