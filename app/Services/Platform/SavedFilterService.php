<?php

namespace App\Services\Platform;

use App\Models\Identity\User;
use App\Models\Platform\SavedFilter;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Enregistrement et retrait des filtres privés (AC 8).
 *
 * Un filtre n'est jamais supprimé, il est désactivé (SOC-03) : l'historique d'audit doit rester
 * interprétable, et une ligne effacée rendrait incompréhensible l'entrée qui la mentionne.
 */
final readonly class SavedFilterService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Enregistre ou met à jour le filtre du **demandeur** pour cette liste. Le propriétaire est
     * pris de l'acteur authentifié, jamais d'une entrée : on ne peut pas créer un filtre au nom
     * d'un autre.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function save(User $owner, string $listKey, string $name, array $criteria): SavedFilter
    {
        return DB::transaction(function () use ($owner, $listKey, $name, $criteria): SavedFilter {
            $existing = SavedFilter::query()
                ->where('owner_id', $owner->getKey())
                ->where('list_key', $listKey)
                ->where('name', trim($name))
                ->lockForUpdate()
                ->first();

            $filter = $existing ?? new SavedFilter([
                'owner_id' => $owner->getKey(),
                'list_key' => $listKey,
                'name' => trim($name),
            ]);
            $filter->fill([
                'criteria' => $criteria,
                'is_active' => true,
                'deactivated_at' => null,
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $filter,
                operation: fn (): bool => $filter->saveOrFail(),
                actorId: (int) $owner->getKey(),
                actorLabel: $this->actorLabel($owner),
                action: $existing === null ? 'saved_filter_created' : 'saved_filter_updated',
                newValues: ['list_key' => $listKey, 'name' => trim($name), 'criteria' => $criteria],
                reason: 'Enregistrement d’un filtre privé.',
            );

            return $filter;
        });
    }

    public function deactivate(SavedFilter $filter, User $actor): SavedFilter
    {
        return DB::transaction(function () use ($filter, $actor): SavedFilter {
            $locked = SavedFilter::query()->whereKey($filter->getKey())->lockForUpdate()->firstOrFail();
            $locked->fill([
                'is_active' => false,
                'deactivated_at' => CarbonImmutable::now('UTC'),
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: fn (): bool => $locked->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'saved_filter_deactivated',
                oldValues: ['is_active' => true],
                newValues: ['is_active' => false],
                reason: 'Retrait d’un filtre privé.',
            );

            return $locked;
        });
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
