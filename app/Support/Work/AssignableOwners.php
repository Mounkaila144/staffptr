<?php

namespace App\Support\Work;

use App\Enums\UserState;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * À qui un acteur peut-il confier un objectif ?
 *
 * La règle reprend exactement celle de `Objective::scopeVisibleTo()` : on ne
 * peut désigner que quelqu'un dont on verrait ensuite les objectifs. Confier un
 * objectif à une personne qu'on ne peut pas suivre n'aurait aucun sens — et
 * permettrait surtout d'inscrire du travail au nom de n'importe qui.
 *
 * Cette classe sert **deux** usages, et c'est délibéré : la liste déroulante du
 * formulaire et la validation côté serveur lisent la même source. Une liste
 * déroulante n'est pas une protection ; c'est la règle de validation qui l'est.
 */
class AssignableOwners
{
    /**
     * Identifiants des comptes que l'acteur peut désigner comme responsable.
     *
     * @return list<int>
     */
    public static function idsFor(User $actor): array
    {
        return self::query($actor)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    /**
     * Les mêmes comptes, avec leur nom, prêts pour un menu déroulant.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function optionsFor(User $actor): array
    {
        return self::query($actor)
            ->with('person:id,full_name')
            ->get()
            ->map(static fn (User $user): array => [
                'id' => (int) $user->getKey(),
                'name' => $user->person->full_name,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /** @return Builder<User> */
    private static function query(User $actor): Builder
    {
        // Les comptes « invités » comptent, et c'est indispensable : l'activation d'un stagiaire
        // exige trois objectifs enregistrés **à son nom**, alors que son compte est encore invité.
        // Les exclure fermait le parcours sur lui-même — aucun objectif n'était assignable, donc
        // aucune activation n'était possible. Suspendus, terminés et archivés restent dehors.
        $query = User::query()->select(['id', 'person_id'])
            ->whereIn('state', [UserState::Actif->value, UserState::Invite->value]);

        if ($actor->hasRole('direction')) {
            return $query;
        }

        if ($actor->hasRole('tuteur')) {
            // Le tuteur : lui-même et son équipe.
            return $query->where(
                fn (Builder $scope): Builder => $scope
                    ->whereKey($actor->getKey())
                    ->orWhere('manager_id', $actor->getKey()),
            );
        }

        // Tout le monde d'autre ne s'engage que pour soi-même. Le compte reste
        // proposé même s'il n'est plus « actif » : sans cela, une personne
        // suspendue verrait une liste vide au lieu d'un refus explicite.
        return $query->whereKey($actor->getKey());
    }

    /**
     * Le compte visé est-il désignable par l'acteur ?
     */
    public static function allows(User $actor, mixed $userId): bool
    {
        return is_numeric($userId) && in_array((int) $userId, self::idsFor($actor), true);
    }
}
