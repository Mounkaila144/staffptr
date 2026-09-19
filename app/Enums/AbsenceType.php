<?php

namespace App\Enums;

enum AbsenceType: string
{
    case Conge = 'conge';
    case Maladie = 'maladie';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Conge => 'Congé',
            self::Maladie => 'Maladie',
            self::Autre => 'Autre',
        };
    }
}
