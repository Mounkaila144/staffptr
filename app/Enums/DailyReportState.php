<?php

namespace App\Enums;

enum DailyReportState: string
{
    case Brouillon = 'brouillon';
    case Envoye = 'envoye';
    case Valide = 'valide';
    case Retourne = 'retourne';
    case EnRetard = 'en_retard';

    public function isSubmitted(): bool
    {
        return in_array($this, [self::Envoye, self::Valide, self::EnRetard], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Envoye => 'Envoyé',
            self::Valide => 'Validé',
            self::Retourne => 'Retourné',
            self::EnRetard => 'Envoyé en retard',
        };
    }
}
