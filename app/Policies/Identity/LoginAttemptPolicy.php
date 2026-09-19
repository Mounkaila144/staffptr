<?php

namespace App\Policies\Identity;

use App\Models\Identity\LoginAttempt;
use App\Models\Identity\User;

class LoginAttemptPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('direction') && $user->can('connexion.consulter');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LoginAttempt $loginAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LoginAttempt $loginAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LoginAttempt $loginAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LoginAttempt $loginAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LoginAttempt $loginAttempt): bool
    {
        return false;
    }
}
