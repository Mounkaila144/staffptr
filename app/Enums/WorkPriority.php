<?php

namespace App\Enums;

enum WorkPriority: string
{
    case Basse = 'basse';
    case Normale = 'normale';
    case Haute = 'haute';
    case Urgente = 'urgente';

    public function label(): string
    {
        return match ($this) {
            self::Basse => 'Basse',
            self::Normale => 'Normale',
            self::Haute => 'Haute',
            self::Urgente => 'Urgente',
        };
    }
}
