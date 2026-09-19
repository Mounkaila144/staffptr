<?php

namespace App\Policies\Finance;

use App\Models\Finance\CorrectionPlan;
use App\Models\Identity\User;

/**
 * Le plan correctif est l'engagement de la direction face à une alerte orange : elle seule le
 * rédige, le valide et le révise. `finance` le consulte au titre du suivi financier (AC 14 à 18).
 *
 * `update` et `delete` répondent `false` sans condition : un plan validé est immuable et rien
 * n'est jamais supprimé (SOC-03, AC 17).
 */
class CorrectionPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']) && $user->can('rapport_financier.consulter');
    }

    public function view(User $user, CorrectionPlan $plan): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('direction') && $user->can('rapport_financier.valider');
    }

    public function validatePlan(User $user, CorrectionPlan $plan): bool
    {
        return $this->create($user) && ! $plan->state->isFrozen();
    }

    public function revise(User $user, CorrectionPlan $plan): bool
    {
        return $this->create($user) && $plan->state->isFrozen();
    }

    public function update(User $user, CorrectionPlan $plan): bool
    {
        return false;
    }

    public function delete(User $user, CorrectionPlan $plan): bool
    {
        return false;
    }
}
