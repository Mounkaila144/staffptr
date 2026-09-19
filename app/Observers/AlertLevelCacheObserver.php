<?php

namespace App\Observers;

use App\Models\Finance\FixedCharge;
use App\Models\Finance\MonthClosure;
use App\Models\Finance\Payment;
use App\Services\Finance\AlertLevelService;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidation du cache d'alerte à l'écriture, jamais par simple expiration (architecture § 19.1).
 *
 * Trois écritures changent le niveau : un encaissement, une charge fixe et une clôture mensuelle.
 * Poser l'invalidation sur l'observateur plutôt que dans chaque service garantit qu'aucun chemin
 * d'écriture — service, correction, contre-écriture, seeder — ne puisse laisser un niveau périmé
 * en cache.
 */
class AlertLevelCacheObserver
{
    public function __construct(private readonly AlertLevelService $alertLevelService) {}

    public function saved(Model $model): void
    {
        $this->forget($model);
    }

    private function forget(Model $model): void
    {
        $this->alertLevelService->forget($this->monthOf($model));
    }

    /**
     * Mois affecté par l'écriture. Une charge fixe n'a pas de mois : elle change l'assiette de
     * tous les mois ouverts, donc au minimum celle du mois courant.
     */
    private function monthOf(Model $model): ?string
    {
        return match (true) {
            $model instanceof Payment => $model->received_on->toDateString(),
            $model instanceof MonthClosure => $model->month->toDateString(),
            $model instanceof FixedCharge => null,
            default => null,
        };
    }
}
