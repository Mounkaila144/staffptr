<?php

namespace App\Support\Auditing;

use Illuminate\Database\Eloquent\Model;
use WeakMap;

class AuditContext
{
    /** @var WeakMap<Model, int> */
    private WeakMap $suppressedModels;

    public function __construct()
    {
        $this->suppressedModels = new WeakMap;
    }

    public function suppressAutomaticAudit(Model $model): void
    {
        $this->suppressedModels[$model] = ($this->suppressedModels[$model] ?? 0) + 1;
    }

    public function releaseAutomaticAudit(Model $model): void
    {
        $remaining = ($this->suppressedModels[$model] ?? 1) - 1;

        if ($remaining < 1) {
            unset($this->suppressedModels[$model]);

            return;
        }

        $this->suppressedModels[$model] = $remaining;
    }

    public function suppressesAutomaticAudit(Model $model): bool
    {
        return ($this->suppressedModels[$model] ?? 0) > 0;
    }
}
