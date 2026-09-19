<?php

namespace App\Policies\Accountability;

use App\Models\Accountability\Internship;
use App\Models\Identity\User;

class InternshipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stagiaire.consulter');
    }

    /**
     * Le stagiaire consulte son dossier, le tuteur ceux de ses stagiaires, `direction` tous ;
     * tout autre accès est refusé, y compris par URL directe (AC 31).
     */
    public function view(User $user, Internship $internship): bool
    {
        return $user->can('stagiaire.consulter')
            && ($internship->user_id === $user->getKey()
                || $internship->tutor_id === $user->getKey()
                || $user->hasRole('direction'));
    }

    /**
     * L'activation d'un stagiaire appartient à `direction` (AC 15, 16).
     */
    public function activate(User $user): bool
    {
        return $user->hasRole('direction') && $user->can('stagiaire.gerer');
    }

    /**
     * Écran de gestion de la charge des tuteurs (AC 25) : ouvert à qui gère les stagiaires.
     */
    public function viewCapacity(User $user): bool
    {
        return $user->can('stagiaire.gerer');
    }

    public function manage(User $user, Internship $internship): bool
    {
        return $user->can('stagiaire.gerer')
            && ($internship->tutor_id === $user->getKey() || $user->hasRole('direction'));
    }

    public function delete(User $user, Internship $internship): bool
    {
        return false;
    }
}
