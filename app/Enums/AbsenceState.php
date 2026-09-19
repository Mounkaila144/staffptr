<?php

namespace App\Enums;

enum AbsenceState: string
{
    case Demandee = 'demandee';
    case Approuvee = 'approuvee';
    case Refusee = 'refusee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Demandee => 'Demandée',
            self::Approuvee => 'Approuvée',
            self::Refusee => 'Refusée',
            self::Annulee => 'Annulée',
        };
    }
}
