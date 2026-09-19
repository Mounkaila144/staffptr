<?php

namespace App\Policies\Identity;

use App\Models\Identity\PersonDocument;
use App\Models\Identity\User;

class PersonDocumentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('fiche.consulter');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PersonDocument $personDocument): bool
    {
        return $user->can('fiche.consulter')
            && PersonDocument::query()
                ->visibleTo($user)
                ->whereKey($personDocument->getKey())
                ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('direction') && $user->can('fiche.gerer');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PersonDocument $personDocument): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PersonDocument $personDocument): bool
    {
        return false;
    }

    public function archive(User $user, PersonDocument $personDocument): bool
    {
        return $this->create($user)
            && PersonDocument::query()
                ->visibleTo($user)
                ->whereKey($personDocument->getKey())
                ->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PersonDocument $personDocument): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PersonDocument $personDocument): bool
    {
        return false;
    }
}
