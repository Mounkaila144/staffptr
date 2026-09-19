<?php

namespace App\Policies\Work;

use App\Models\Identity\User;
use App\Models\Work\CompanyPriority;

class CompanyPriorityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('objectif_entreprise.consulter');
    }

    public function view(User $user, CompanyPriority $priority): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('objectif_entreprise.gerer');
    }

    public function update(User $user, CompanyPriority $priority): bool
    {
        return $this->create($user);
    }

    public function cancel(User $user, CompanyPriority $priority): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, CompanyPriority $priority): bool
    {
        return false;
    }
}
