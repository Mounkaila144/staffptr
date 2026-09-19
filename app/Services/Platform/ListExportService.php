<?php

namespace App\Services\Platform;

use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Support\Auditing\AuditLogger;
use App\Support\Listing\ListRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Export CSV des listes principales (AC 13 à 18, AC 35).
 *
 * **Le format.** CSV uniquement — ni PDF ni Excel en MVP (FR108, FR175). Le contrat de fichier
 * arrêté par la Task 1 vise un ouverture sans manipulation dans un tableur francophone :
 * séparateur `;`, encodage UTF-8 **avec BOM**, guillemets doubles, fin de ligne CRLF. Le
 * point-virgule parce qu'Excel en locale française l'attend ; le BOM parce que sans lui Excel lit
 * l'UTF-8 comme du Latin-1 et casse les accents. Les montants sortent en entiers XOF bruts pour
 * rester additionnables (AC 18).
 *
 * **Le périmètre.** La requête vient de {@see ListingService::exportableQuery()}, c'est-à-dire de
 * la même méthode que l'écran. Il n'existe aucun chemin par lequel l'export verrait une ligne que
 * l'écran cache (AC 14, AC 15, AC 35).
 *
 * **L'audit.** Il est écrit **avant** que la moindre ligne ne soit produite, dans sa propre
 * transaction validée. Un export qui échouerait en cours de flux laisse donc quand même sa trace :
 * ce qui compte pour FR176 est de savoir que quelqu'un a demandé ces données, pas que le
 * téléchargement soit allé au bout.
 */
final readonly class ListExportService
{
    public const SEPARATOR = ';';

    public const ENCLOSURE = '"';

    /** UTF-8 BOM : sans lui, un tableur francophone affiche « DÃ©penses ». */
    public const BOM = "\xEF\xBB\xBF";

    public const LINE_ENDING = "\r\n";

    /**
     * Seuil de « gros volume » au-delà duquel l'export part en file (AC 17).
     *
     * Valeur retenue faute d'arbitrage : 5 000 lignes. Elle est calée sur le budget de connexion
     * faible — au-delà, la génération et le transfert dépassent le temps qu'un utilisateur en 3G
     * dégradée peut attendre sans que la requête n'expire.
     */
    public const QUEUE_THRESHOLD = 5000;

    public function __construct(
        private ListingService $listing,
        private ListRegistry $registry,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Prépare un export : compte les lignes **du périmètre du demandeur**, écrit l'audit, et dit
     * si le téléchargement peut se faire dans la requête ou doit passer en file.
     *
     * @param  array<string, mixed>  $filters
     * @return array{query: Builder<covariant \Illuminate\Database\Eloquent\Model>, row_count: int, filename: string, queued: bool, list_key: string, label: string}
     */
    public function prepare(User $actor, string $listKey, array $filters, ?string $sort, ?string $direction): array
    {
        $source = $this->registry->get($listKey);

        // Deuxième barrière, après celle de la route : la permission de l'écran est exactement
        // celle de l'export (PERM-06). Un export ne peut pas exister sans son écran.
        if (! $actor->can($source->permission())) {
            throw new LogicException("L'export de « {$source->label()} » exige la permission de la liste.");
        }

        $query = $this->listing->exportableQuery($actor, $listKey, $filters, $sort, $direction);
        $rowCount = (clone $query)->count();

        $this->audit($actor, $source->label(), $rowCount, $filters);

        return [
            'query' => $query,
            'row_count' => $rowCount,
            'filename' => $this->filename($listKey),
            'queued' => $rowCount > self::QUEUE_THRESHOLD,
            'list_key' => $listKey,
            'label' => $source->label(),
        ];
    }

    /**
     * Écrit le flux CSV. `cursor()` évite de charger le jeu complet en mémoire : un export de
     * capacité doit tenir dans les 4 Go du VPS sans se soucier du nombre de lignes.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public function writeCsv(string $listKey, Builder $query): int
    {
        $source = $this->registry->get($listKey);
        $stream = fopen('php://output', 'wb');

        if ($stream === false) {
            throw new LogicException("Le flux d'export ne peut pas être ouvert.");
        }

        fwrite($stream, self::BOM);
        $this->writeRow($stream, $source->csvHeaders());

        foreach ($query->cursor() as $record) {
            $this->writeRow($stream, $source->csvRow($record));
        }

        return fclose($stream) ? 0 : 1;
    }

    /**
     * Contenu CSV complet en mémoire — utilisé par le travail en file et par les tests, qui ont
     * besoin de comparer le fichier à l'écran ligne à ligne (AC 14).
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public function renderCsv(string $listKey, Builder $query): string
    {
        $source = $this->registry->get($listKey);
        $lines = [$this->formatRow($source->csvHeaders())];

        foreach ($query->cursor() as $record) {
            $lines[] = $this->formatRow($source->csvRow($record));
        }

        return self::BOM.implode(self::LINE_ENDING, $lines).self::LINE_ENDING;
    }

    public function filename(string $listKey): string
    {
        $stamp = CarbonImmutable::now('Africa/Niamey')->format('Y-m-d_H-i');

        return "ptr-staff_{$listKey}_{$stamp}.csv";
    }

    /**
     * AC 16, FR176 : auteur, nature des données et nombre de lignes. L'audit est synchrone et
     * transactionnel ; son échec annule l'export (SOC-02).
     *
     * @param  array<string, mixed>  $filters
     */
    private function audit(User $actor, string $label, int $rowCount, array $filters): void
    {
        $subject = new AuditLog;

        DB::connection($subject->getConnectionName())->transaction(function () use ($actor, $subject, $label, $rowCount, $filters): void {
            $this->auditLogger->record(
                actorId: (int) $actor->getKey(),
                actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
                auditable: $subject,
                action: 'list_exported',
                newValues: [
                    'data_nature' => $label,
                    'row_count' => $rowCount,
                    'filters' => array_filter(
                        $filters,
                        static fn (mixed $value): bool => $value !== null && $value !== '',
                    ),
                ],
                reason: "Export CSV de « {$label} ».",
            );
        });
    }

    /**
     * @param  resource  $stream
     * @param  list<string|int|null>  $row
     */
    private function writeRow($stream, array $row): void
    {
        fwrite($stream, $this->formatRow($row).self::LINE_ENDING);
    }

    /**
     * Échappement CSV explicite plutôt que `fputcsv()` : celui-ci impose `\n` comme fin de ligne,
     * alors que le contrat de fichier demande CRLF pour un tableur Windows.
     *
     * @param  list<string|int|null>  $row
     */
    private function formatRow(array $row): string
    {
        $cells = array_map(function (string|int|null $cell): string {
            $value = (string) ($cell ?? '');

            return self::ENCLOSURE.str_replace(self::ENCLOSURE, self::ENCLOSURE.self::ENCLOSURE, $value).self::ENCLOSURE;
        }, $row);

        return implode(self::SEPARATOR, $cells);
    }
}
