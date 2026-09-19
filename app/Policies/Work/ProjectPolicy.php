<?php

namespace App\Policies\Work;

use App\Models\Identity\User;
use App\Models\Work\Project;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projet.consulter');
    }

    public function view(User $user, Project $project): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('projet.gerer');
    }

    public function update(User $user, Project $project): bool
    {
        return $this->create($user) || (int) $project->manager_id === (int) $user->getKey();
    }

    public function viewBudget(User $user, Project $project): bool
    {
        return $user->can('projet.budget.consulter');
    }

    public function delete(User $user, Project $project): bool
    {
        return false;
    }
}
