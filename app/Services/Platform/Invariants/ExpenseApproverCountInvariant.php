<?php

namespace App\Services\Platform\Invariants;

use App\Models\Identity\User;
use Throwable;

class ExpenseApproverCountInvariant implements InvariantCheck
{
    private const NAME = 'Nombre de comptes approbateurs de dépenses';

    public function check(): InvariantResult
    {
        try {
            $count = User::permission('depense.approuver')->count();
        } catch (Throwable) {
            return InvariantResult::fail(
                self::NAME,
                'comptes approbateurs illisibles',
                'exactement 2 comptes porteurs de depense.approuver',
            );
        }

        $observed = "{$count} compte".($count === 1 ? '' : 's').' porteur(s) de depense.approuver';

        return $count === 2
            ? InvariantResult::pass(self::NAME, $observed, 'exactement 2 comptes')
            : InvariantResult::fail(self::NAME, $observed, 'exactement 2 comptes');
    }
}
