<?php

namespace App\Services\Platform;

use App\Models\Identity\User;
use App\Support\Listing\ListRegistry;
use App\Support\Listing\ListSource;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtres, tri et pagination des listes principales (AC 7, 10, 11, 12).
 *
 * Le service ne connaît aucune liste en particulier : il applique aux `ListSource` la mécanique
 * commune. Deux garanties y sont centralisées plutôt que répétées.
 *
 * 1. **Le tri est une liste blanche.** Une colonne absente de `sortableColumns()` retombe sur le
 *    tri par défaut. Un paramètre `sort=password` ou `sort=(select …)` n'atteint jamais SQL.
 * 2. **Le vide par filtre ne se confond pas avec le vide réel** (AC 10, SOC-06). La distinction
 *    coûte une requête de plus — le total sans filtre — mais c'est la seule façon de dire à
 *    quelqu'un « il n'y a rien » plutôt que « vos filtres ne laissent rien passer », qui appellent
 *    des gestes opposés.
 */
final readonly class ListingService
{
    public const PER_PAGE = 25;

    public function __construct(private ListRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{key: string, label: string, headers: list<string>, items: list<array<string, mixed>>, total: int, unfiltered_total: int, active_filters: list<array{key: string, label: string}>, filters_active: bool, sort: string, direction: string, sortable: list<string>, page: int, per_page: int, last_page: int, empty_message: string, export_url: string}
     */
    public function listing(User $viewer, string $listKey, array $filters, ?string $sort, ?string $direction, int $page = 1): array
    {
        $source = $this->registry->get($listKey);
        $query = $source->query($viewer, $filters);
        $sort = $this->resolveSort($source, $sort);
        $direction = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

        $total = (clone $query)->count();
        $unfilteredTotal = $source->query($viewer, [])->count();
        $activeFilters = $source->activeFilterLabels($filters);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $lastPage));

        $records = $query
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->forPage($page, self::PER_PAGE)
            ->get();

        return [
            'key' => $source->key(),
            'label' => $source->label(),
            'headers' => $source->csvHeaders(),
            // L'écran affiche **les mêmes cellules** que l'export produira. Ce n'est pas une
            // commodité de rendu : c'est ce qui rend l'AC 14 vérifiable ligne à ligne, puisque le
            // test peut comparer directement le CSV à ce que la page a reçu.
            'items' => $records->map(static fn ($record): array => [
                'id' => (int) $record->getKey(),
                'cells' => $source->csvRow($record),
            ])->all(),
            'total' => $total,
            'unfiltered_total' => $unfilteredTotal,
            'active_filters' => $activeFilters,
            'filters_active' => $activeFilters !== [],
            'sort' => $sort,
            'direction' => $direction,
            'sortable' => $source->sortableColumns(),
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'last_page' => $lastPage,
            'empty_message' => $this->emptyMessage($source, $activeFilters !== [], $unfilteredTotal),
            'export_url' => route('exports.create', ['liste' => $source->key()], absolute: false),
        ];
    }

    /**
     * Requête exportable : **exactement** celle de l'écran, tri compris, sans pagination.
     *
     * C'est le point unique par lequel passent l'écran et l'export. L'AC 14 est donc structurel :
     * il n'existe pas d'autre chemin où une différence pourrait s'introduire.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function exportableQuery(User $viewer, string $listKey, array $filters, ?string $sort, ?string $direction): Builder
    {
        $source = $this->registry->get($listKey);
        $resolvedSort = $this->resolveSort($source, $sort);
        $resolvedDirection = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

        return $source->query($viewer, $filters)
            ->orderBy($resolvedSort, $resolvedDirection)
            ->orderBy('id', $resolvedDirection);
    }

    /**
     * Un tri hors liste blanche n'est pas une erreur bloquante : il retombe silencieusement sur le
     * tri par défaut. L'utilisateur voit sa liste, et le paramètre hostile n'a aucun effet.
     */
    private function resolveSort(ListSource $source, ?string $sort): string
    {
        return in_array($sort, $source->sortableColumns(), true) ? (string) $sort : $source->defaultSort();
    }

    /** AC 10, SOC-06 : trois vides différents, trois messages différents. */
    private function emptyMessage(ListSource $source, bool $filtersActive, int $unfilteredTotal): string
    {
        if ($filtersActive) {
            return 'Aucun résultat avec ces filtres. Réinitialisez-les pour revoir toute la liste.';
        }

        if ($unfilteredTotal === 0) {
            return "Aucune donnée dans « {$source->label()} » pour le moment.";
        }

        return 'Aucun résultat sur cette page.';
    }
}
