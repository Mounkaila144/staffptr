<?php

namespace App\Support\Listing;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Contrat d'une « liste principale » : filtres, tri et export.
 *
 * **La raison d'être de cette interface est l'AC 14.** L'export doit appliquer *exactement* les
 * mêmes restrictions que l'écran d'origine. La seule façon d'en être sûr n'est pas de relire deux
 * implémentations et de les comparer, c'est de n'en avoir qu'une : {@see query()} est appelée par
 * l'écran **et** par l'export, sans variante. Une liste qui filtrerait différemment à l'export ne
 * peut pas exister, parce qu'il n'y a pas d'endroit où l'écrire.
 */
interface ListSource
{
    /** Clé stable de la liste, telle qu'elle apparaît en URL et dans `saved_filters.list_key`. */
    public function key(): string;

    /**
     * Nature des données, en français. Elle est consignée telle quelle dans l'entrée d'audit de
     * l'export (AC 16).
     */
    public function label(): string;

    /** Permission exigée pour voir la liste — donc aussi pour l'exporter. */
    public function permission(): string;

    /**
     * Requête **déjà restreinte au périmètre du demandeur**, filtres appliqués.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<covariant Model>
     */
    public function query(User $viewer, array $filters): Builder;

    /**
     * Filtres acceptés, avec leurs règles de validation. Tout paramètre absent de cette liste est
     * ignoré : un paramètre d'URL inventé ne peut pas élargir le périmètre (AC 9, AC 15).
     *
     * @return array<string, list<string>>
     */
    public function filterRules(): array;

    /**
     * Libellés des filtres, pour les afficher en permanence et les retirer un par un (AC 11).
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{key: string, label: string}>
     */
    public function activeFilterLabels(array $filters): array;

    /**
     * Colonnes triables. Une colonne absente de cette liste est refusée : le tri ne doit pas
     * devenir un canal d'injection ni de divulgation de schéma.
     *
     * @return list<string>
     */
    public function sortableColumns(): array;

    /** Colonne de tri par défaut. */
    public function defaultSort(): string;

    /**
     * En-têtes CSV, dans l'ordre des colonnes de {@see row()}.
     *
     * @return list<string>
     */
    public function csvHeaders(): array;

    /**
     * Une ligne CSV. Les montants sortent en **entiers XOF bruts**, jamais formatés ni suffixés :
     * un tableur doit pouvoir les additionner (convention monétaire opposable, AC 18).
     *
     * @return list<string|int|null>
     */
    public function csvRow(Model $record): array;
}
