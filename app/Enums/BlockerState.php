<?php

namespace App\Enums;

enum BlockerState: string
{
    case Ouvert = 'ouvert';
    case PrisEnCharge = 'pris_en_charge';
    case Resolu = 'resolu';
    case FermeSansSolution = 'ferme_sans_solution';
}
