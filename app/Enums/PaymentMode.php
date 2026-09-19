<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Especes = 'especes';
    case Banque = 'banque';
    case MobileMoney = 'mobile_money';
    case Autre = 'autre';
}
