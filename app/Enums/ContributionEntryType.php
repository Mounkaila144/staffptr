<?php

namespace App\Enums;

/**
 * Sens d'une ligne du registre des parts.
 *
 * Les parts ne s'effacent pas : un encaissement annulé ne supprime rien, il ajoute une écriture
 * inverse datée. Le total d'un directeur est la somme des émissions moins celle des annulations.
 */
enum ContributionEntryType: string
{
    case Emission = 'emission';
    case Annulation = 'annulation';

    public function label(): string
    {
        return match ($this) {
            self::Emission => 'Émission',
            self::Annulation => 'Annulation',
        };
    }
}
