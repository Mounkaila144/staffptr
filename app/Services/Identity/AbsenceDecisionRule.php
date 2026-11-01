<?php

namespace App\Services\Identity;

use App\Enums\AbsenceState;
use App\Enums\UserState;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qui décide d'une absence (décision du propriétaire du 2026-11-01).
 *
 * - Le demandeur a un responsable direct actif : lui seul décide (règle d'origine, inchangée).
 * - Le demandeur n'a pas de responsable direct actif : tout compte direction actif, autre que le
 *   demandeur, décide.
 * - Personne ne décide jamais de sa propre absence.
 */
class AbsenceDecisionRule
{
    public const DIRECTION_ROUTE_LABEL = 'Sans responsable direct : décision par la direction';

    public function __construct(private readonly HierarchyService $hierarchyService) {}

    public function activeManager(Absence $absence): ?User
    {
        return $this->hierarchyService->directRelations($absence->user)['manager'];
    }

    public function isDecidedByDirection(Absence $absence): bool
    {
        return ! $this->activeManager($absence) instanceof User;
    }

    public function canDecide(User $actor, Absence $absence): bool
    {
        if ((int) $absence->user_id === (int) $actor->getKey()) {
            return false;
        }

        $manager = $this->activeManager($absence);

        if ($manager instanceof User) {
            return (int) $manager->getKey() === (int) $actor->getKey();
        }

        return $this->isActiveDirection($actor);
    }

    /**
     * Absences en attente que l'acteur peut décider : celles de ses subordonnés directs et, pour un
     * compte direction actif, celles des personnes sans responsable direct actif.
     *
     * @return Builder<Absence>
     */
    public function pendingFor(User $actor): Builder
    {
        $isDirection = $this->isActiveDirection($actor);

        return Absence::query()
            ->where('state', AbsenceState::Demandee)
            ->where('user_id', '!=', $actor->getKey())
            ->whereHas('user', function (Builder $user) use ($actor, $isDirection): void {
                $user->where(function (Builder $decidable) use ($actor, $isDirection): void {
                    $decidable->whereHas('manager', function (Builder $manager) use ($actor): void {
                        $manager->whereKey($actor->getKey())->where('state', UserState::Actif->value);
                    });

                    if ($isDirection) {
                        $decidable->orWhereDoesntHave('manager', function (Builder $manager): void {
                            $manager->where('state', UserState::Actif->value);
                        });
                    }
                });
            });
    }

    private function isActiveDirection(User $actor): bool
    {
        return $actor->state === UserState::Actif && $actor->hasRole('direction');
    }
}
