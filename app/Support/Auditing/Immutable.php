<?php

namespace App\Support\Auditing;

trait Immutable
{
    protected static function bootImmutable(): void
    {
        static::updating(function (): never {
            throw ImmutableRecordException::forOperation('modifiée');
        });

        static::deleting(function (): never {
            throw ImmutableRecordException::forOperation('supprimée');
        });
    }
}
