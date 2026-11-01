<?php

namespace App\Support\Listing;

use BackedEnum;
use DateTimeImmutable;

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

    /**
     * Champ de liste fermée construit à partir d'un enum doté de `label()`.
     *
     * @param  list<BackedEnum>  $cases
     * @return array{key: string, label: string, type: string, options: list<array{value: string, label: string}>}
     */
    protected function enumField(string $key, string $label, array $cases): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'select',
            'options' => array_map(
                static fn (BackedEnum $case): array => [
                    'value' => (string) $case->value,
                    'label' => method_exists($case, 'label') ? (string) $case->label() : (string) $case->value,
                ],
                $cases,
            ),
        ];
    }

    /** @return array{key: string, label: string, type: string, options: list<array{value: string, label: string}>} */
    protected function dateField(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'date', 'options' => []];
    }

    /** Date de filtre lisible (« 01/08/2026 ») ; une valeur mal formée est rendue telle quelle. */
    protected function displayDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? $value : $date->format('d/m/Y');
    }
}
