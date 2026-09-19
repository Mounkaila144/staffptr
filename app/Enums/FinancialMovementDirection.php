<?php

namespace App\Enums;

enum FinancialMovementDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
