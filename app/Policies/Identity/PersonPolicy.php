<?php

namespace App\Policies\Identity;

use App\Models\Identity\Person;
use App\Models\Identity\User;

class PersonPolicy
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
    public function view(User $user, Person $person): bool
    {
        return $this->canReadAccounts($user)
            && Person::query()->visibleTo($user)->whereKey($person->getKey())->exists();
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
    public function update(User $user, Person $person): bool
    {
        return $this->canManageAccounts($user)
            && Person::query()->visibleTo($user)->whereKey($person->getKey())->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Person $person): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Person $person): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Person $person): bool
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
