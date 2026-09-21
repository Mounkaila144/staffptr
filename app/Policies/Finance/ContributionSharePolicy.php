<?php

namespace App\Policies\Finance;

use App\Models\Finance\ContributionShare;
use App\Models\Identity\User;

/**
 * Le registre des parts est réservé aux directeurs — c'est leur propriété dans l'entreprise qu'il
 * mesure. Aucun autre rôle n'y accède, y compris par URL directe.
 */
class ContributionSharePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('direction') && $user->can('part_contribution.consulter');
    }

    public function view(User $user, ContributionShare $contributionShare): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContributionShare $contributionShare): bool
    {
        return false;
    }

    public function delete(User $user, ContributionShare $contributionShare): bool
    {
        return false;
    }
}
