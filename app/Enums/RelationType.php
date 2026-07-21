<?php

namespace App\Enums;

enum RelationType: string
{
    case Dirigeant = 'dirigeant';
    case Employe = 'employe';
    case Contractuel = 'contractuel';
    case Stagiaire = 'stagiaire';

    public function label(): string
    {
        return match ($this) {
            self::Dirigeant => 'Dirigeant',
            self::Employe => 'Employé',
            self::Contractuel => 'Contractuel',
            self::Stagiaire => 'Stagiaire',
        };
    }
}
