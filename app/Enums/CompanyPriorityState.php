<?php

namespace App\Enums;

enum CompanyPriorityState: string
{
    case Validee = 'validee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return $this === self::Validee ? 'Validée' : 'Annulée';
    }
}
