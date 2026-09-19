<?php

namespace App\Policies\Finance;

use App\Models\Finance\Account;
use App\Models\Identity\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFinancialRole($user) && $user->can('compte_financier.consulter');
    }

    public function view(User $user, Account $account): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->isFinancialRole($user) && $user->can('compte_financier.gerer');
    }

    public function update(User $user, Account $account): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Account $account): bool
    {
        return false;
    }

    public function restore(User $user, Account $account): bool
    {
        return false;
    }

    public function forceDelete(User $user, Account $account): bool
    {
        return false;
    }

    private function isFinancialRole(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']);
    }
}
