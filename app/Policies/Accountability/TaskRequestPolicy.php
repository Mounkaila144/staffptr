<?php

namespace App\Policies\Accountability;

use App\Models\Accountability\TaskRequest;
use App\Models\Identity\User;

class TaskRequestPolicy
{
    public function process(User $user, TaskRequest $taskRequest): bool
    {
        return $user->can('tache.gerer')
            && ($taskRequest->responsible_id === $user->getKey() || $user->hasRole('direction'));
    }

    public function delete(User $user, TaskRequest $taskRequest): bool
    {
        return false;
    }
}
