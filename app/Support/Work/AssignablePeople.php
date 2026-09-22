<?php

namespace App\Support\Work;

use App\Enums\UserState;
use App\Models\Identity\User;

/**
 * Annuaire des personnes à qui un travail peut être confié — projet, tâche, priorité, livrable.
 *
 * Ces quatre formulaires demandaient d'**écrire l'identifiant numérique** du responsable. Personne
 * ne connaît par cœur l'identifiant de ses collègues : le champ était donc soit deviné, soit laissé
 * de côté, soit rempli faux sans que rien ne le signale — `Rule::exists('users', 'id')` accepte
 * n'importe quel compte existant, y compris archivé.
 *
 * La liste est **plus étroite que ce que le serveur accepte**, jamais plus large : tout ce qu'elle
 * propose sera validé. L'inverse serait un piège, comme le rappelle {@see AssignableOwners}.
 *
 * À ne pas confondre avec {@see AssignableOwners}, qui répond à une autre question : *qui l'acteur
 * a-t-il le droit de désigner* sur un objectif, périmètre par rôle. Ici, aucune règle d'accès n'est
 * ajoutée ni retirée — seul le mode de saisie change.
 */
final class AssignablePeople
{
    /**
     * Comptes actifs ou invités, triés par nom.
     *
     * Les invités comptent : un stagiaire dont le compte attend l'activation se voit déjà confier
     * des objectifs, et rien ne justifie qu'un projet ou une tâche lui soit refusé. Les comptes
     * suspendus, terminés et archivés restent dehors — leur confier du travail n'aurait pas de sens.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function options(): array
    {
        return User::query()
            ->select(['id', 'person_id'])
            ->whereIn('state', [UserState::Actif->value, UserState::Invite->value])
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
}
