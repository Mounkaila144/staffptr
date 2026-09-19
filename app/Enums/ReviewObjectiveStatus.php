<?php

namespace App\Enums;

enum ReviewObjectiveStatus: string
{
    case Atteint = 'atteint';
    case PartiellementAtteint = 'partiellement_atteint';
    case NonAtteint = 'non_atteint';

    public function label(): string
    {
        return match ($this) {
            self::Atteint => 'Atteint',
            self::PartiellementAtteint => 'Partiellement atteint',
            self::NonAtteint => 'Non atteint',
        };
    }

    /**
     * La cause de l'écart n'est demandée que lorsque le résultat s'écarte de l'objectif (AC 3).
     */
    public function requiresGapCause(): bool
    {
        return $this !== self::Atteint;
    }
}
