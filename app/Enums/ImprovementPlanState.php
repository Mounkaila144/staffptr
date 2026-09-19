<?php

namespace App\Enums;

enum ImprovementPlanState: string
{
    case EnCours = 'en_cours';
    case Termine = 'termine';

    public function label(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Termine => 'Terminé',
        };
    }
}
