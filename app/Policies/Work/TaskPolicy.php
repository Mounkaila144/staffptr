<?php

namespace App\Policies\Work;

use App\Models\Identity\User;
use App\Models\Work\Task;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tache.consulter');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->viewAny($user) && ($user->hasRole('direction') || (int) $task->assignee_id === (int) $user->getKey() || (int) $task->created_by === (int) $user->getKey() || (int) $task->project?->manager_id === (int) $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->can('tache.gerer');
    }

    public function update(User $user, Task $task): bool
    {
        return $this->view($user, $task) && $user->can('tache.gerer');
    }

    public function delete(User $user, Task $task): bool
    {
        return false;
    }
}
