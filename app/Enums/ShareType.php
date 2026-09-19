<?php

namespace App\Enums;

enum ShareType: string
{
    case Contributor = 'contributor';
    case Executor = 'executor';
    case PtrNiger = 'ptr_niger';
}
