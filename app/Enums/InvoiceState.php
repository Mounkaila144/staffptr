<?php

namespace App\Enums;

enum InvoiceState: string
{
    case Impayee = 'impayee';
    case PartiellementPayee = 'partiellement_payee';
    case Payee = 'payee';
    case Annulee = 'annulee';
}
