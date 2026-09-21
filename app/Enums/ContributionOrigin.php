<?php

namespace App\Enums;

/**
 * Ce qui a fait naître une part de contribution.
 *
 * Les deux formes sont mises sur le même pied par le barème de millésime : vendre sans références
 * la première année est aussi difficile que d'y risquer son argent.
 */
enum ContributionOrigin: string
{
    case Encaissement = 'encaissement';
    case Apport = 'apport';

    public function label(): string
    {
        return match ($this) {
            self::Encaissement => 'Encaissement',
            self::Apport => 'Apport d’argent personnel',
        };
    }
}
