<?php

namespace App\Policies\Finance;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Identity\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('depense.consulter');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->viewAny($user)
            && Expense::query()->visibleTo($user)->whereKey($expense->getKey())->exists();
    }

    public function create(User $user): bool
    {
        // AC 3: creation is open to all active authenticated users
        // This is handled by authentication_routes in authorization matrix, not by permission
        return (int) $user->getKey() > 0;
    }

    public function update(User $user, Expense $expense): bool
    {
        // Requester can update their own pending expenses only
        return $this->viewAny($user)
            && (int) $expense->requester_id === (int) $user->getKey()
            && $expense->state === ExpenseState::Demandee;
    }

    public function cancel(User $user, Expense $expense): bool
    {
        // Requester or direction can cancel pending expenses
        if (! $this->viewAny($user) || $expense->state !== ExpenseState::Demandee) {
            return false;
        }

        // Requester can cancel their own
        if ((int) $expense->requester_id === (int) $user->getKey()) {
            return true;
        }

        // La direction peut annuler toute demande en attente. Le test portait sur
        // `depense.consulter`, que tout le monde détient : n'importe quel compte pouvait annuler
        // la demande d'un autre par une requête directe.
        return $user->can('depense.approuver');
    }

    public function pay(User $user, Expense $expense): bool
    {
        return $user->hasRole('finance')
            && $user->can('depense.payer')
            && $expense->state === ExpenseState::Approuvee;
    }

    public function cancelPayment(User $user, Expense $expense): bool
    {
        return $user->hasRole('finance')
            && $user->can('depense.payer')
            && $expense->state === ExpenseState::Payee;
    }

    /** Régularisation d'un paiement enregistré sans justificatif (FR124). */
    public function attachPaymentProof(User $user, Expense $expense): bool
    {
        return $user->hasRole('finance')
            && $user->can('depense.payer')
            && $expense->state === ExpenseState::Payee
            && $expense->counter_entry_of_id === null
            && $expense->payment_attachment_id === null;
    }

    public function approve(User $user, Expense $expense): bool
    {
        return $user->can('depense.approuver')
            && (int) $expense->requester_id !== (int) $user->getKey();
    }

    public function viewApproval(User $user, Expense $expense): bool
    {
        return $this->approve($user, $expense);
    }

    public function refuse(User $user, Expense $expense): bool
    {
        return $this->approve($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        // AC 5: no physical deletion, only cancellation with reason
        return false;
    }

    public function restore(User $user, Expense $expense): bool
    {
        return false;
    }

    public function forceDelete(User $user, Expense $expense): bool
    {
        // AC 5: no physical deletion
        return false;
    }
}
