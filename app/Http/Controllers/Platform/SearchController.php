<?php

namespace App\Http\Controllers\Platform;

use App\Enums\ObjectiveState;
use App\Enums\PersonOperationalStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SearchRequest;
use App\Models\Identity\User;
use App\Services\Platform\SearchService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recherche transverse (AC 1 à 6).
 *
 * Le contrôleur autorise, délègue et répond. Aucun filtrage de périmètre ici : il appartient aux
 * scopes des modules propriétaires, appelés par {@see SearchService}.
 */
class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function __invoke(SearchRequest $request): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);

        return Inertia::render('Platform/Search', [
            'results' => $this->search->search($viewer, $request->filters()),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    /**
     * Statuts proposés par type de résultat. Ils sont envoyés une fois avec la page — quelques
     * dizaines d'octets — plutôt que d'être devinés côté client.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function statusOptions(): array
    {
        return [
            'person' => array_map(
                static fn (PersonOperationalStatus $case): array => ['value' => $case->value, 'label' => $case->label()],
                PersonOperationalStatus::cases(),
            ),
            'project' => array_map(
                static fn (ProjectStatus $case): array => ['value' => $case->value, 'label' => $case->label()],
                ProjectStatus::cases(),
            ),
            'objective' => array_map(
                static fn (ObjectiveState $case): array => ['value' => $case->value, 'label' => $case->label()],
                ObjectiveState::cases(),
            ),
        ];
    }
}
