<?php

namespace App\Enums;

enum FinancialAccountType: string
{
    case Caisse = 'caisse';
    case Banque = 'banque';
    case MobileMoney = 'mobile_money';
}
