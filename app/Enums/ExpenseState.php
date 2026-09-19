<?php

namespace App\Enums;

enum ExpenseState: string
{
    case Demandee = 'demandee';
    case Approuvee = 'approuvee';
    case Payee = 'payee';
    case Refusee = 'refusee';
    case Annulee = 'annulee';
}
