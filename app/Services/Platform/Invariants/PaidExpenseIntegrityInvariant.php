<?php

namespace App\Services\Platform\Invariants;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class PaidExpenseIntegrityInvariant implements InvariantCheck
{
    public function check(): InvariantResult
    {
        try {
            $ids = Expense::query()
                ->where('state', ExpenseState::Payee->value)
                ->where(function (Builder $query): void {
                    $query->whereNull('account_id')
                        ->orWhereNull('paid_on')
                        ->orWhereNull('payment_mode')
                        ->orWhereNull('paid_by')
                        ->orWhereNull('paid_at')
                        ->orWhereNull('payment_idempotency_key');
                })
                ->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        } catch (Throwable) {
            return InvariantResult::fail('Intégrité des dépenses payées', 'données illisibles', 'tous les champs de paiement obligatoires renseignés');
        }

        $observed = $ids === [] ? 'aucune dépense payée incomplète' : 'dépenses incomplètes : #'.implode(', #', $ids);

        return $ids === []
            ? InvariantResult::pass('Intégrité des dépenses payées', $observed, 'tous les champs de paiement obligatoires renseignés')
            : InvariantResult::fail('Intégrité des dépenses payées', $observed, 'tous les champs de paiement obligatoires renseignés');
    }
}
