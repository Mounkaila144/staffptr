<?php

namespace App\Policies\Finance;

use App\Models\Finance\ReserveMovement;
use App\Models\Identity\User;

class ReserveMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']) && $user->can('reserve.consulter');
    }

    public function view(User $user, ReserveMovement $movement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']) && $user->canAny(['reserve.gerer', 'reserve.preparer']);
    }

    public function approve(User $user, ReserveMovement $movement): bool
    {
        return $user->hasRole('direction') && $user->can('reserve.gerer');
    }

    public function delete(User $user, ReserveMovement $movement): bool
    {
        return false;
    }
}
