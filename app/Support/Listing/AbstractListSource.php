<?php

namespace App\Support\Listing;

/**
 * Comportement commun à toutes les listes principales.
 *
 * La seule mécanique mutualisée est la lecture d'un filtre : un filtre absent, vide ou fourni sous
 * forme de tableau vaut « pas de filtre ». Normaliser ici évite que chaque liste réinvente sa
 * tolérance aux paramètres d'URL hostiles — c'est exactement le genre d'écart qui finit par créer
 * une différence entre l'écran et l'export (AC 14, AC 15).
 */
abstract class AbstractListSource implements ListSource
{
    /** @param array<string, mixed> $filters */
    protected function filterValue(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        if ($value === null || is_array($value) || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }
}
