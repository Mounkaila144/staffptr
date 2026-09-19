<?php

namespace App\Policies\Finance;

use App\Models\Finance\Payment;
use App\Models\Identity\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFinancialRole($user) && $user->can('encaissement.consulter');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->isFinancialRole($user) && $user->can('encaissement.gerer');
    }

    public function correct(User $user, Payment $payment): bool
    {
        return $this->create($user);
    }

    public function cancel(User $user, Payment $payment): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    private function isFinancialRole(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']);
    }
}
