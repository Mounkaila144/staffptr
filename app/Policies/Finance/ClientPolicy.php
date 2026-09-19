<?php

namespace App\Policies\Finance;

use App\Models\Finance\Client;
use App\Models\Identity\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFinancialRole($user) && $user->can('client.consulter');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Client $client): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->isFinancialRole($user) && $user->can('client.gerer');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Client $client): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Client $client): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Client $client): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Client $client): bool
    {
        return false;
    }

    private function isFinancialRole(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']);
    }
}
