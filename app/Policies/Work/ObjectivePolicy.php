<?php

namespace App\Policies\Work;

use App\Models\Identity\User;
use App\Models\Work\Objective;

class ObjectivePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('objectif_individuel.consulter');
    }

    public function view(User $user, Objective $objective): bool
    {
        return $this->viewAny($user) && Objective::query()->visibleTo($user)->whereKey($objective->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('objectif_individuel.gerer');
    }

    public function update(User $user, Objective $objective): bool
    {
        return $this->view($user, $objective) && ($user->hasRole('direction') || (int) $objective->user_id === (int) $user->getKey() || (int) $objective->owner()->value('manager_id') === (int) $user->getKey());
    }

    public function validate(User $user, Objective $objective): bool
    {
        return $this->view($user, $objective) && $user->can('objectif.valider') && ($user->hasRole('direction') || (int) $objective->owner()->value('manager_id') === (int) $user->getKey());
    }

    public function comment(User $user, Objective $objective): bool
    {
        return $this->update($user, $objective);
    }

    public function delete(User $user, Objective $objective): bool
    {
        return false;
    }
}
