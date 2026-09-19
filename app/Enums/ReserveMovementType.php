<?php

namespace App\Enums;

enum ReserveMovementType: string
{
    case Allocation = 'allocation';
    case Usage = 'usage';
    case Reconstitution = 'reconstitution';
    case Reversal = 'reversal';
}
