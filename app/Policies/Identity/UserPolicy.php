<?php

namespace App\Policies\Identity;

use App\Models\Identity\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canReadAccounts($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $this->canReadAccounts($user)
            && User::query()->visibleTo($user)->whereKey($model->getKey())->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canManageAccounts($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $this->canManageAccounts($user)
            && User::query()->visibleTo($user)->whereKey($model->getKey())->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    private function canReadAccounts(User $user): bool
    {
        return $user->hasAnyPermission([
            'compte.consulter',
            'compte.gerer',
            'compte.technique.gerer',
        ]);
    }

    private function canManageAccounts(User $user): bool
    {
        return $user->hasAnyPermission(['compte.gerer', 'compte.technique.gerer']);
    }
}
