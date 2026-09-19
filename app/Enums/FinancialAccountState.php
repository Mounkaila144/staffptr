<?php

namespace App\Enums;

enum FinancialAccountState: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Libellé textuel : aucune information n'est portée par la couleur seule (SOC-10, NFR31).
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Inactive => 'Inactif',
        };
    }
}
