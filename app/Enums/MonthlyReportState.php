<?php

namespace App\Enums;

enum MonthlyReportState: string
{
    case Draft = 'draft';
    case Controlled = 'controlled';
    case Validated = 'validated';
}
