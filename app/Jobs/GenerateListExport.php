<?php

namespace App\Jobs;

use App\Models\Identity\User;
use App\Notifications\ListExportReadyNotification;
use App\Services\Platform\ListExportService;
use App\Services\Platform\ListingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Génération d'un export volumineux hors du cycle HTTP (AC 17).
 *
 * **Le périmètre est réévalué à l'exécution, pas figé à la demande.** Le travail ne transporte pas
 * de requête sérialisée : il porte l'identifiant du demandeur et ses filtres, et reconstruit la
 * requête au moment de produire le fichier. Si les droits de la personne ont changé entre la
 * demande et la génération, le fichier reflète ses droits **du moment de la génération** — un
 * export ne doit jamais devenir une capture de permissions périmées (AC 35, AC 37 de 9.1).
 *
 * **Idempotence.** Le fichier est écrit sous un chemin déterministe, dérivé du demandeur, de la
 * liste et des filtres. Rejouer le travail réécrit le même fichier au lieu d'en accumuler.
 */
class GenerateListExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string, mixed> $filters */
    public function __construct(
        private readonly int $requesterId,
        private readonly string $listKey,
        private readonly array $filters,
        private readonly ?string $sort,
        private readonly ?string $direction,
    ) {}

    public function handle(ListExportService $exports, ListingService $listing): void
    {
        $requester = User::query()->find($this->requesterId);

        // Le compte a pu être suspendu ou archivé depuis la demande : on ne produit alors rien.
        if (! $requester instanceof User) {
            return;
        }

        $query = $listing->exportableQuery($requester, $this->listKey, $this->filters, $this->sort, $this->direction);
        $contents = $exports->renderCsv($this->listKey, $query);
        $path = $this->path($exports);

        // `local` pointe sur storage/app/private : hors racine web (NFR15).
        Storage::disk('local')->put($path, $contents);

        $requester->notify(new ListExportReadyNotification(
            $this->listKey,
            $exports->filename($this->listKey),
        ));
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    private function path(ListExportService $exports): string
    {
        $fingerprint = substr(hash('sha256', json_encode([
            $this->requesterId,
            $this->listKey,
            $this->filters,
            $this->sort,
            $this->direction,
        ], JSON_THROW_ON_ERROR)), 0, 16);

        return "exports/{$this->requesterId}/{$this->listKey}-{$fingerprint}.csv";
    }
}
