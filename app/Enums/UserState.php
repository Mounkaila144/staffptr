<?php

namespace App\Enums;

enum UserState: string
{
    case Invite = 'invite';
    case Actif = 'actif';
    case Suspendu = 'suspendu';
    case Termine = 'termine';
    case Archive = 'archive';
}
