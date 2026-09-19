<?php

namespace App\Policies\Finance;

use App\Models\Finance\Reconciliation;
use App\Models\Identity\User;

class ReconciliationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']) && $user->can('rapprochement.consulter');
    }

    public function view(User $user, Reconciliation $item): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']) && $user->can('rapprochement.preparer');
    }

    public function validate(User $user, Reconciliation $item): bool
    {
        return $user->hasAnyRole(['direction', 'finance']) && $user->can('rapprochement.controler');
    }

    public function correct(User $user, Reconciliation $item): bool
    {
        return $this->create($user);
    }

    public function update(User $user, Reconciliation $item): bool
    {
        return false;
    }

    public function delete(User $user, Reconciliation $item): bool
    {
        return false;
    }
}
