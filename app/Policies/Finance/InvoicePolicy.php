<?php

namespace App\Policies\Finance;

use App\Models\Finance\Invoice;
use App\Models\Identity\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFinancialRole($user) && $user->can('facture.consulter');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->isFinancialRole($user) && $user->can('facture.gerer');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $this->create($user);
    }

    private function isFinancialRole(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']);
    }
}
