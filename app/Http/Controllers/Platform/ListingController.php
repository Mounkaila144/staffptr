<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ListingRequest;
use App\Models\Identity\User;
use App\Models\Platform\SavedFilter;
use App\Services\Platform\ListingService;
use App\Support\Listing\ListRegistry;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran générique des listes principales : filtres, tri, pagination (AC 7 à 12).
 *
 * L'écran est unique pour les quatre listes. Ce n'est pas une économie de code, c'est la seule
 * façon de garantir que la mécanique de filtres est identique partout : une liste ne peut pas
 * dériver et devenir plus permissive que les autres.
 */
class ListingController extends Controller
{
    public function __construct(
        private readonly ListingService $listing,
        private readonly ListRegistry $registry,
    ) {}

    public function __invoke(ListingRequest $request): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);

        $listKey = $request->listKey();
        $filters = $request->filters();

        return Inertia::render('Platform/Listing', [
            'listing' => $this->listing->listing(
                $viewer,
                $listKey,
                $filters,
                $request->query('sort') === null ? null : (string) $request->query('sort'),
                $request->query('direction') === null ? null : (string) $request->query('direction'),
                (int) ($request->query('page') ?? 1),
            ),
            'filterFields' => array_keys($this->registry->get($listKey)->filterRules()),
            'appliedFilters' => $filters,
            // Filtres enregistrés du **demandeur seul** : le scope l'impose, pas le contrôleur.
            'savedFilters' => $viewer->can('tableau_bord_global.consulter')
                ? SavedFilter::query()
                    ->visibleTo($viewer)
                    ->active()
                    ->where('list_key', $listKey)
                    ->orderBy('name')
                    ->get(['id', 'name', 'criteria'])
                    ->map(static fn (SavedFilter $filter): array => [
                        'id' => (int) $filter->getKey(),
                        'name' => $filter->name,
                        'criteria' => $filter->criteria,
                    ])
                    ->all()
                : [],
            'canSaveFilter' => $viewer->can('create', SavedFilter::class),
        ]);
    }
}
