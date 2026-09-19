<?php

namespace App\Policies\Platform;

use App\Models\Identity\User;
use App\Models\Platform\InternalDocumentVersion;

class InternalDocumentVersionPolicy
{
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
    public function view(User $user, InternalDocumentVersion $internalDocumentVersion): bool
    {
        return $user->can('document_interne.consulter');
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
    public function update(User $user, InternalDocumentVersion $internalDocumentVersion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InternalDocumentVersion $internalDocumentVersion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InternalDocumentVersion $internalDocumentVersion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InternalDocumentVersion $internalDocumentVersion): bool
    {
        return false;
    }
}
