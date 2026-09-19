<?php

namespace App\Enums;

enum InternshipEvaluationType: string
{
    case Hebdomadaire = 'hebdomadaire';
    case Finale = 'finale';

    public function label(): string
    {
        return match ($this) {
            self::Hebdomadaire => 'Évaluation hebdomadaire',
            self::Finale => 'Évaluation finale',
        };
    }

    /**
     * Seule l'évaluation finale porte l'indication d'éligibilité à l'attestation (AC 29).
     */
    public function carriesCertificateEligibility(): bool
    {
        return $this === self::Finale;
    }
}
