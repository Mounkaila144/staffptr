<?php

namespace App\Enums;

enum WorkTaskStatus: string
{
    case AFaire = 'a_faire';
    case EnCours = 'en_cours';
    case Bloquee = 'bloquee';
    case Terminee = 'terminee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::AFaire => 'À faire', self::EnCours => 'En cours', self::Bloquee => 'Bloquée',
            self::Terminee => 'Terminée', self::Annulee => 'Annulée',
        };
    }
}
