<?php

namespace App\Policies\Finance;

use App\Models\Finance\FixedCharge;
use App\Models\Identity\User;

class FixedChargePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFinancialRole($user) && $user->can('charge_fixe.consulter');
    }

    public function view(User $user, FixedCharge $fixedCharge): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->isFinancialRole($user) && $user->can('charge_fixe.gerer');
    }

    public function update(User $user, FixedCharge $fixedCharge): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, FixedCharge $fixedCharge): bool
    {
        return false;
    }

    private function isFinancialRole(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']);
    }
}
