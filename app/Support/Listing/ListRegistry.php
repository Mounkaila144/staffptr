<?php

namespace App\Support\Listing;

use App\Support\Listing\Sources\DailyReportListSource;
use App\Support\Listing\Sources\ExpenseListSource;
use App\Support\Listing\Sources\ObjectiveListSource;
use App\Support\Listing\Sources\PersonListSource;
use InvalidArgumentException;

/**
 * Inventaire des « listes principales » du MVP (contrat figé par la Task 1 de la story 10.1).
 *
 * La story demandait un inventaire arrêté avec le PO. Faute d'arbitrage disponible, l'inventaire
 * retenu est consigné ici et dans le Dev Agent Record plutôt que laissé implicite, conformément à
 * l'instruction de la story sur les décisions non arbitrées.
 *
 * **Critère de sélection** : une liste est « principale » si elle est consultée hors de son propre
 * périmètre — c'est-à-dire si son contenu dépend du rôle du lecteur. C'est exactement là que
 * filtres et exports peuvent fuir, et donc là que la garantie a de la valeur. Les quatre listes
 * retenues couvrent les quatre modules qui portent une visibilité ligne à ligne :
 *
 * | Clé            | Module           | Scope de visibilité              |
 * |----------------|------------------|----------------------------------|
 * | `depenses`     | `Finance`        | `Expense::visibleTo()`           |
 * | `objectifs`    | `Work`           | `Objective::visibleTo()`         |
 * | `rapports`     | `Accountability` | `DailyReport::visibleTo()`       |
 * | `personnes`    | `Identity`       | `Person::visibleTo()`            |
 *
 * Les écrans de paramétrage — catégories, charges fixes, jours fériés — en sont exclus : ils sont
 * identiques pour tous ceux qui y ont droit, il n'y a rien à cloisonner.
 */
final class ListRegistry
{
    public const EXPENSES = 'depenses';

    public const OBJECTIVES = 'objectifs';

    public const DAILY_REPORTS = 'rapports';

    public const PEOPLE = 'personnes';

    public function __construct(
        private readonly ExpenseListSource $expenses,
        private readonly ObjectiveListSource $objectives,
        private readonly DailyReportListSource $dailyReports,
        private readonly PersonListSource $people,
    ) {}

    /**
     * Toutes les listes, indexées par clé.
     *
     * @return array<string, ListSource>
     */
    public function all(): array
    {
        return [
            self::EXPENSES => $this->expenses,
            self::OBJECTIVES => $this->objectives,
            self::DAILY_REPORTS => $this->dailyReports,
            self::PEOPLE => $this->people,
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return [self::EXPENSES, self::OBJECTIVES, self::DAILY_REPORTS, self::PEOPLE];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Une clé inconnue est une erreur, pas un cas à tolérer : une liste inventée en URL ne doit
     * jamais retomber sur une liste par défaut.
     */
    public function get(string $key): ListSource
    {
        $source = $this->all()[$key] ?? null;

        if (! $source instanceof ListSource) {
            throw new InvalidArgumentException("Liste inconnue : {$key}.");
        }

        return $source;
    }
}
