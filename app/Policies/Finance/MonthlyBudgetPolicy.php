<?php

namespace App\Policies\Finance;

use App\Models\Finance\MonthlyBudget;
use App\Models\Identity\User;

class MonthlyBudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']) && $user->can('budget_financier.consulter');
    }

    public function view(User $user, MonthlyBudget $budget): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']) && $user->can('budget_financier.gerer');
    }

    public function update(User $user, MonthlyBudget $budget): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, MonthlyBudget $budget): bool
    {
        return false;
    }
}
