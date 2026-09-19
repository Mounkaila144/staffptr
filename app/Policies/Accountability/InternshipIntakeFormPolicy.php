<?php

namespace App\Policies\Accountability;

use App\Enums\InternshipIntakeState;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Identity\User;

class InternshipIntakeFormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stagiaire.consulter');
    }

    /**
     * La fiche est visible du candidat, de son responsable, de son tuteur et de `direction`.
     */
    public function view(User $user, InternshipIntakeForm $internshipIntakeForm): bool
    {
        if (! $user->can('stagiaire.consulter')) {
            return false;
        }

        return $user->hasRole('direction')
            || $internshipIntakeForm->candidate_user_id === $user->getKey()
            || $internshipIntakeForm->manager_id === $user->getKey()
            || $internshipIntakeForm->tutor_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->can('stagiaire.gerer');
    }

    public function submit(User $user, InternshipIntakeForm $internshipIntakeForm): bool
    {
        return $user->can('stagiaire.gerer')
            && $internshipIntakeForm->state === InternshipIntakeState::Brouillon
            && ($user->hasRole('direction')
                || $internshipIntakeForm->manager_id === $user->getKey()
                || $internshipIntakeForm->tutor_id === $user->getKey());
    }

    /**
     * L'approbation est réservée à `direction`, en une seule étape (AC 15).
     */
    public function decide(User $user, InternshipIntakeForm $internshipIntakeForm): bool
    {
        return $user->hasRole('direction')
            && $user->can('stagiaire.gerer')
            && $internshipIntakeForm->state === InternshipIntakeState::Soumise;
    }

    public function delete(User $user, InternshipIntakeForm $internshipIntakeForm): bool
    {
        return false;
    }
}
