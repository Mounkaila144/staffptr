<?php

namespace App\Observers;

use App\Support\Auditing\AuditContext;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function __construct(
        private readonly AuditLogger $logger,
        private readonly AuditContext $context,
    ) {}

    public function created(Model $model): void
    {
        $this->recordUnlessExplicit($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $newValues = $model->getChanges();
        $oldValues = [];

        foreach (array_keys($newValues) as $attribute) {
            $oldValues[$attribute] = $model->getRawOriginal($attribute);
        }

        $this->recordUnlessExplicit($model, 'updated', $oldValues, $newValues);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordUnlessExplicit(
        Model $model,
        string $action,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        if ($this->context->suppressesAutomaticAudit($model)) {
            return;
        }

        $this->logger->record(
            actorId: null,
            actorLabel: 'Système — filet de sécurité',
            auditable: $model,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }
}
