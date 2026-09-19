<?php

namespace App\Policies\Platform;

use App\Models\Identity\User;
use App\Models\Platform\InternalDocument;

class InternalDocumentPolicy
{
    private function manage(User $user): bool
    {
        return $user->hasRole('direction') && $user->can('document_interne.gerer');
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('document_interne.consulter');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InternalDocument $internalDocument): bool
    {
        return $user->can('document_interne.consulter');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InternalDocument $internalDocument): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InternalDocument $internalDocument): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InternalDocument $internalDocument): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InternalDocument $internalDocument): bool
    {
        return false;
    }

    public function acknowledge(User $user, InternalDocument $internalDocument): bool
    {
        return $user->can('document_interne.consulter');
    }

    public function viewAcknowledgements(User $user, InternalDocument $internalDocument): bool
    {
        return $this->manage($user);
    }
}
