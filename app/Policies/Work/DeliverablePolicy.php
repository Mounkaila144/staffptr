<?php

namespace App\Policies\Work;

use App\Models\Identity\User;
use App\Models\Work\Deliverable;

class DeliverablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('livrable.consulter');
    }

    public function view(User $user, Deliverable $deliverable): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('livrable.gerer');
    }

    public function update(User $user, Deliverable $deliverable): bool
    {
        return $this->create($user) && ($user->hasRole('direction') || (int) $deliverable->owner_id === (int) $user->getKey() || (int) $deliverable->project()->value('manager_id') === (int) $user->getKey());
    }

    public function validate(User $user, Deliverable $deliverable): bool
    {
        return $user->hasRole('direction') || (int) $deliverable->project()->value('manager_id') === (int) $user->getKey();
    }

    public function delete(User $user, Deliverable $deliverable): bool
    {
        return false;
    }
}
