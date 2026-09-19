<?php

namespace App\Enums;

enum PersonOperationalStatus: string
{
    case Actif = 'actif';
    case Absent = 'absent';
    case Suspendu = 'suspendu';
    case Sorti = 'sorti';

    public function label(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::Absent => 'Absent',
            self::Suspendu => 'Suspendu',
            self::Sorti => 'Sorti',
        };
    }
}
