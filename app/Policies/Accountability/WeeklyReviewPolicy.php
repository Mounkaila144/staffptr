<?php

namespace App\Policies\Accountability;

use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;

class WeeklyReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('revue_hebdomadaire.consulter');
    }

    /**
     * La revue est visible de la personne évaluée, de son responsable et de `direction` (AC 5).
     */
    public function view(User $user, WeeklyReview $weeklyReview): bool
    {
        return $user->can('revue_hebdomadaire.consulter')
            && ($weeklyReview->subject_user_id === $user->getKey()
                || $weeklyReview->reviewer_id === $user->getKey()
                || $user->hasRole('direction'));
    }

    public function create(User $user): bool
    {
        return $user->can('revue_hebdomadaire.gerer');
    }

    /**
     * Une revue s'ouvre pour un membre de son équipe ; `direction` conduit la revue de tout
     * compte, y compris celui d'un associé, mais jamais la sienne (AC 1, 5).
     */
    public function open(User $user, User $subject): bool
    {
        if (! $user->can('revue_hebdomadaire.gerer') || $subject->is($user)) {
            return false;
        }

        return $user->hasRole('direction') || $subject->manager_id === $user->getKey();
    }

    /**
     * Seul le responsable qui conduit la revue en renseigne le contenu, et uniquement tant
     * qu'elle n'est pas validée (AC 7).
     */
    public function update(User $user, WeeklyReview $weeklyReview): bool
    {
        return $user->can('revue_hebdomadaire.gerer')
            && $weeklyReview->reviewer_id === $user->getKey()
            && $weeklyReview->isEditable();
    }

    /**
     * Les deux parties commentent, et elles seules (AC 4).
     */
    public function comment(User $user, WeeklyReview $weeklyReview): bool
    {
        return $user->can('revue_hebdomadaire.consulter')
            && $weeklyReview->isEditable()
            && ($weeklyReview->subject_user_id === $user->getKey()
                || $weeklyReview->reviewer_id === $user->getKey());
    }

    /**
     * Les deux parties valident, et elles seules. `direction` ne dispose d'aucune voie
     * dérobée lorsqu'elle n'est pas partie à la revue (AC 4, 42).
     */
    public function validateReview(User $user, WeeklyReview $weeklyReview): bool
    {
        return $user->can('revue_hebdomadaire.consulter')
            && $weeklyReview->isEditable()
            && ($weeklyReview->subject_user_id === $user->getKey()
                || $weeklyReview->reviewer_id === $user->getKey());
    }

    /**
     * Aucune revue n'est supprimable (SOC-03).
     */
    public function delete(User $user, WeeklyReview $weeklyReview): bool
    {
        return false;
    }
}
