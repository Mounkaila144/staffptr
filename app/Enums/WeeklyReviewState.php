<?php

namespace App\Enums;

enum WeeklyReviewState: string
{
    case Brouillon = 'brouillon';
    case EnAttenteValidation = 'en_attente_validation';
    case Validee = 'validee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnAttenteValidation => 'En attente de validation',
            self::Validee => 'Validée',
        };
    }

    /**
     * Une revue validée est figée : plus aucune écriture n'est acceptée (AC 7).
     */
    public function isEditable(): bool
    {
        return $this !== self::Validee;
    }
}
