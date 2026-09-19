<?php

namespace App\Policies\Accountability;

use App\Enums\ImprovementPlanState;
use App\Models\Accountability\ImprovementPlan;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;

class ImprovementPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('revue_hebdomadaire.consulter');
    }

    /**
     * Visible de la personne concernée, de son responsable et de `direction`, et de personne
     * d'autre — y compris par URL directe (AC 11).
     */
    public function view(User $user, ImprovementPlan $improvementPlan): bool
    {
        if (! $user->can('revue_hebdomadaire.consulter')) {
            return false;
        }

        return $improvementPlan->subject_user_id === $user->getKey()
            || $user->hasRole('direction')
            || $improvementPlan->subject()->where('manager_id', $user->getKey())->exists();
    }

    /**
     * Un plan se crée depuis une revue, par la personne qui la conduit (AC 9).
     */
    public function createFrom(User $user, WeeklyReview $weeklyReview): bool
    {
        return $user->can('revue_hebdomadaire.gerer')
            && ($weeklyReview->reviewer_id === $user->getKey() || $user->hasRole('direction'));
    }

    /**
     * La clôture consigne un résultat constaté ; elle appartient au responsable ou à
     * `direction`, jamais à la personne accompagnée.
     */
    public function close(User $user, ImprovementPlan $improvementPlan): bool
    {
        if (! $user->can('revue_hebdomadaire.gerer') || $improvementPlan->state !== ImprovementPlanState::EnCours) {
            return false;
        }

        return $user->hasRole('direction')
            || $improvementPlan->created_by === $user->getKey()
            || $improvementPlan->subject()->where('manager_id', $user->getKey())->exists();
    }

    /**
     * Aucun plan n'est supprimable (SOC-03).
     */
    public function delete(User $user, ImprovementPlan $improvementPlan): bool
    {
        return false;
    }
}
