<?php

namespace App\Policies\Identity;

use App\Models\Identity\JobFunction;
use App\Models\Identity\User;

class JobFunctionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->managesOrganization($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, JobFunction $jobFunction): bool
    {
        return $this->managesOrganization($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->managesOrganization($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, JobFunction $jobFunction): bool
    {
        return $this->managesOrganization($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, JobFunction $jobFunction): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, JobFunction $jobFunction): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, JobFunction $jobFunction): bool
    {
        return false;
    }

    private function managesOrganization(User $user): bool
    {
        return $user->hasRole('direction') && $user->can('organisation.gerer');
    }
}
