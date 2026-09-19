<?php

namespace App\Services\Work;

use App\Enums\DeliverableStatus;
use App\Models\Identity\User;
use App\Models\Work\Deliverable;
use App\Models\Work\DeliverableStatusHistory;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeliverableService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Deliverable
    {
        $deliverable = new Deliverable;

        return DB::connection($deliverable->getConnectionName())->transaction(function () use ($deliverable, $data, $actor): Deliverable {
            $deliverable->fill($data);
            $this->auditLogger->runExplicitly($deliverable, fn (): bool => $deliverable->saveOrFail(), $actor->getKey(), $this->actorLabel($actor), 'deliverable_created', newValues: $deliverable->getAttributes());

            return $deliverable;
        });
    }

    public function transition(Deliverable $deliverable, DeliverableStatus $target, string $reason, User $actor): Deliverable
    {
        return DB::connection($deliverable->getConnectionName())->transaction(function () use ($deliverable, $target, $reason, $actor): Deliverable {
            $locked = Deliverable::query()->with('project')->whereKey($deliverable->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => "Le passage de {$from->label()} vers {$target->label()} n’est pas autorisé."]);
            }
            if ($target === DeliverableStatus::Valide && ! $actor->hasRole('direction') && (int) $locked->project->manager_id !== (int) $actor->getKey()) {
                throw new AuthorizationException('Seul le responsable du projet ou la direction peut valider ce livrable.');
            }
            $locked->status = $target;
            if ($target === DeliverableStatus::Soumis && $locked->actual_date === null) {
                $locked->actual_date = CarbonImmutable::now('Africa/Niamey')->startOfDay();
            }
            $this->auditLogger->runExplicitly($locked, fn (): bool => $locked->saveOrFail(), $actor->getKey(), $this->actorLabel($actor), 'deliverable_status_changed', ['status' => $from->value], ['status' => $target->value, 'actual_date' => $locked->actual_date?->toDateString()], $reason);
            DeliverableStatusHistory::query()->create(['deliverable_id' => $locked->getKey(), 'from_status' => $from, 'to_status' => $target, 'actor_id' => $actor->getKey(), 'reason' => $reason, 'changed_at' => CarbonImmutable::now('UTC')]);

            return $locked->refresh();
        });
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
