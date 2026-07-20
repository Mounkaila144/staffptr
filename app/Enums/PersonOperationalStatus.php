<?php

namespace App\Enums;

enum PersonOperationalStatus: string
{
    case Actif = 'actif';
    case Absent = 'absent';
    case Suspendu = 'suspendu';
    case Sorti = 'sorti';
}
