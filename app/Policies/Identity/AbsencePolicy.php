<?php

namespace App\Policies\Identity;

use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Services\Identity\HierarchyService;

class AbsencePolicy
{
    public function __construct(private readonly HierarchyService $hierarchyService) {}

    public function viewAny(User $user): bool
    {
        return $user->can('absence.consulter');
    }

    public function view(User $user, Absence $absence): bool
    {
        return $this->viewAny($user)
            && Absence::query()->visibleTo($user)->whereKey($absence->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Absence $absence): bool
    {
        return false;
    }

    public function approve(User $user, Absence $absence): bool
    {
        return $this->canDecide($user, $absence);
    }

    public function refuse(User $user, Absence $absence): bool
    {
        return $this->canDecide($user, $absence);
    }

    public function cancel(User $user, Absence $absence): bool
    {
        return $this->viewAny($user) && (int) $absence->user_id === (int) $user->getKey();
    }

    public function delete(User $user, Absence $absence): bool
    {
        return false;
    }

    public function restore(User $user, Absence $absence): bool
    {
        return false;
    }

    public function forceDelete(User $user, Absence $absence): bool
    {
        return false;
    }

    private function canDecide(User $user, Absence $absence): bool
    {
        if (! $this->viewAny($user) || (int) $absence->user_id === (int) $user->getKey()) {
            return false;
        }

        $manager = $this->hierarchyService->directRelations($absence->user)['manager'];

        return $manager instanceof User && (int) $manager->getKey() === (int) $user->getKey();
    }
}
