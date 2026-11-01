<?php

namespace App\Enums;

enum ExpenseState: string
{
    case Demandee = 'demandee';
    case Approuvee = 'approuvee';
    case Payee = 'payee';
    case Refusee = 'refusee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Demandee => 'Demandée',
            self::Approuvee => 'Approuvée',
            self::Payee => 'Payée',
            self::Refusee => 'Refusée',
            self::Annulee => 'Annulée',
        };
    }
}
