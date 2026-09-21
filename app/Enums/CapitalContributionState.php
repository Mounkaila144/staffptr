<?php

namespace App\Enums;

/**
 * Cycle de vie d'un apport d'argent personnel d'un directeur.
 *
 * L'enregistrement vaut accord de son auteur ; l'apport n'émet de parts qu'une fois approuvé par
 * l'autre directeur. Aucun état de remboursement n'existe : un apport approuvé est définitif.
 */
enum CapitalContributionState: string
{
    case EnAttente = 'en_attente';
    case Approuve = 'approuve';
    case Refuse = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente du second directeur',
            self::Approuve => 'Approuvé',
            self::Refuse => 'Refusé',
        };
    }
}
