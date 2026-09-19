<?php

namespace App\Enums;

enum ContractState: string
{
    case Active = 'active';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
