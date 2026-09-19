<?php

namespace App\Enums;

enum ReconciliationState: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Corrected = 'corrected';
}
