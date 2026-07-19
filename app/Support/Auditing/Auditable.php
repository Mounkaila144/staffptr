<?php

namespace App\Support\Auditing;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            app(AuditableObserver::class)->created($model);
        });
        static::updated(function (Model $model): void {
            app(AuditableObserver::class)->updated($model);
        });
    }

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        $connection = $this->getConnection();

        if ($connection->transactionLevel() > 0) {
            return parent::save($options);
        }

        return $connection->transaction(
            fn (): bool => parent::save($options),
        );
    }
}
