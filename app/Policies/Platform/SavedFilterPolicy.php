<?php

namespace App\Policies\Platform;

use App\Models\Identity\User;
use App\Models\Platform\SavedFilter;

/**
 * Un filtre enregistré appartient à son auteur (AC 8).
 *
 * `direction` seule peut en créer, conformément à l'AC 8. Mais la propriété prime sur le rôle :
 * même un compte `direction` ne voit pas le filtre d'un autre compte `direction`. C'est pourquoi
 * `view()` compare les identifiants au lieu de se contenter du rôle.
 */
class SavedFilterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tableau_bord_global.consulter');
    }

    public function view(User $user, SavedFilter $filter): bool
    {
        return (int) $filter->owner_id === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('direction') && $user->can('tableau_bord_global.consulter');
    }

    public function update(User $user, SavedFilter $filter): bool
    {
        return $this->view($user, $filter);
    }

    /** Aucune suppression physique : un filtre se désactive (SOC-03). */
    public function delete(User $user, SavedFilter $filter): bool
    {
        return false;
    }
}
