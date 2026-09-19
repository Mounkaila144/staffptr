<?php

namespace App\Enums;

enum InternshipIntakeState: string
{
    case Brouillon = 'brouillon';
    case Soumise = 'soumise';
    case Approuvee = 'approuvee';
    case Refusee = 'refusee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Soumise => 'Soumise',
            self::Approuvee => 'Approuvée',
            self::Refusee => 'Refusée',
        };
    }
}
