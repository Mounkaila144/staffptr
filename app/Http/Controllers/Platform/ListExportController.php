<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ListingRequest;
use App\Jobs\GenerateListExport;
use App\Models\Identity\User;
use App\Services\Platform\ListExportService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV d'une liste principale (AC 13 à 18, AC 35).
 *
 * Le contrôleur réutilise `ListingRequest` : **la même validation, les mêmes filtres et la même
 * autorisation que l'écran**. C'est délibéré — un export doté de sa propre Form Request finirait
 * tôt ou tard par accepter un paramètre que l'écran refuse.
 */
class ListExportController extends Controller
{
    public function __construct(private readonly ListExportService $exports) {}

    public function __invoke(ListingRequest $request): StreamedResponse|RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $listKey = $request->listKey();
        $sort = $request->query('sort') === null ? null : (string) $request->query('sort');
        $direction = $request->query('direction') === null ? null : (string) $request->query('direction');

        $export = $this->exports->prepare($actor, $listKey, $request->filters(), $sort, $direction);

        // AC 17 : au-delà du seuil, la génération quitte le cycle HTTP. L'utilisateur reçoit une
        // réponse immédiate plutôt qu'une requête qui expire.
        if ($export['queued']) {
            GenerateListExport::dispatch(
                (int) $actor->getKey(),
                $listKey,
                $request->filters(),
                $sort,
                $direction,
            );

            return back()->with(
                'success',
                "L'export de « {$export['label']} » ({$export['row_count']} lignes) est en préparation. Vous recevrez une notification dès qu'il sera disponible.",
            );
        }

        return response()->streamDownload(
            fn (): int => $this->exports->writeCsv($listKey, $export['query']),
            $export['filename'],
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
