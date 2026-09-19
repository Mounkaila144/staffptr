<?php

namespace App\Enums;

/**
 * États d'un plan correctif exigé par le niveau d'alerte orange (FR163, story 9.1 AC 14 à 18).
 *
 * Un plan validé est définitivement figé : sa révision crée une nouvelle version liée par
 * `previous_id`, elle ne réécrit jamais l'original (SOC-03, architecture § 15.2). L'original
 * conserve donc son état `valide` pour toujours ; c'est le numéro de version le plus élevé du mois
 * qui désigne le plan courant.
 */
enum CorrectionPlanState: string
{
    case Brouillon = 'brouillon';
    case Valide = 'valide';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Valide => 'Validé',
        };
    }

    /** Un plan validé n'accepte plus aucune modification directe (AC 17). */
    public function isFrozen(): bool
    {
        return $this === self::Valide;
    }
}
