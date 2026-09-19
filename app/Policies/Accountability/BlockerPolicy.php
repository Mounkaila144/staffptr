<?php

namespace App\Policies\Accountability;

use App\Models\Accountability\Blocker;
use App\Models\Identity\User;

class BlockerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('blocage.consulter');
    }

    public function view(User $user, Blocker $blocker): bool
    {
        return $user->can('blocage.consulter') && ($user->hasRole('direction') || in_array($user->getKey(), [$blocker->created_by, $blocker->solicited_user_id], true));
    }

    public function create(User $user): bool
    {
        return $user->can('blocage.creer');
    }

    public function transition(User $user, Blocker $blocker): bool
    {
        return $user->can('blocage.gerer') && ($user->hasRole('direction') || $blocker->solicited_user_id === $user->getKey());
    }

    public function delete(User $user, Blocker $blocker): bool
    {
        return false;
    }
}
