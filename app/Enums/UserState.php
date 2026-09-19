<?php

namespace App\Enums;

enum UserState: string
{
    case Invite = 'invite';
    case Actif = 'actif';
    case Suspendu = 'suspendu';
    case Termine = 'termine';
    case Archive = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::Invite => 'Invité',
            self::Actif => 'Actif',
            self::Suspendu => 'Suspendu',
            self::Termine => 'Terminé',
            self::Archive => 'Archivé',
        };
    }
}
