<?php

namespace App\Enums;

enum TaskRequestState: string
{
    case Ouvert = 'ouvert';
    case Traite = 'traite';
}
