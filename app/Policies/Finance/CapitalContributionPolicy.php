<?php

namespace App\Policies\Finance;

use App\Models\Finance\CapitalContribution;
use App\Models\Identity\User;

/**
 * Un apport d'argent personnel se déclare et se tranche entre directeurs, et entre eux seuls.
 */
class CapitalContributionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('direction') && $user->can('part_contribution.consulter');
    }

    public function view(User $user, CapitalContribution $capitalContribution): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('direction') && $user->can('part_contribution.gerer');
    }

    /** Le second directeur tranche ; l'auteur de l'apport ne s'approuve jamais lui-même. */
    public function decide(User $user, CapitalContribution $capitalContribution): bool
    {
        return $this->create($user) && (int) $capitalContribution->contributor_id !== (int) $user->getKey();
    }

    public function update(User $user, CapitalContribution $capitalContribution): bool
    {
        return $this->decide($user, $capitalContribution);
    }

    public function delete(User $user, CapitalContribution $capitalContribution): bool
    {
        return false;
    }
}
