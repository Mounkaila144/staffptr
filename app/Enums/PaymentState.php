<?php

namespace App\Enums;

enum PaymentState: string
{
    case Validated = 'validated';
    case Corrected = 'corrected';
    case Cancelled = 'cancelled';
}
