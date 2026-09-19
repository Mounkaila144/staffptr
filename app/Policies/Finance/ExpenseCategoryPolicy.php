<?php

namespace App\Policies\Finance;

use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;

class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->managesFinanceParameters($user);
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $this->managesFinanceParameters($user);
    }

    public function create(User $user): bool
    {
        return $this->managesFinanceParameters($user);
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $this->managesFinanceParameters($user);
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return false;
    }

    public function restore(User $user, ExpenseCategory $category): bool
    {
        return false;
    }

    public function forceDelete(User $user, ExpenseCategory $category): bool
    {
        return false;
    }

    private function managesFinanceParameters(User $user): bool
    {
        return $user->can('parametre.gerer');
    }
}
