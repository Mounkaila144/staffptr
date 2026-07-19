<?php

namespace App\Support\Auditing;

use LogicException;

class ImmutableRecordException extends LogicException
{
    public static function forOperation(string $operation): self
    {
        return new self("Une entrée d'audit ne peut pas être {$operation}.");
    }
}
