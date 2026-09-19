<?php

namespace App\Support\Listing\Sources;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Identity\User;
use App\Support\Listing\AbstractListSource;
use App\Support\Listing\ListRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Liste principale « Dépenses » (module `Finance`).
 *
 * Le périmètre vient entièrement de `Expense::visibleTo()` : `direction` et `finance` voient tout,
 * un demandeur ne voit que ses propres demandes. L'export réutilise cette même méthode, ce qui
 * rend l'AC 15 vrai par construction — aucun paramètre d'URL ne peut élargir ce que le scope a
 * déjà restreint.
 */
final class ExpenseListSource extends AbstractListSource
{
    public function key(): string
    {
        return ListRegistry::EXPENSES;
    }

    public function label(): string
    {
        return 'Dépenses';
    }

    public function permission(): string
    {
        return 'depense.consulter';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Expense>
     */
    public function query(User $viewer, array $filters): Builder
    {
        $query = Expense::query()
            ->visibleTo($viewer)
            ->with(['requester.person:id,full_name', 'category:id,name']);

        if (($state = $this->filterValue($filters, 'state')) !== null) {
            $query->where('state', $state);
        }

        if (($category = $this->filterValue($filters, 'category_id')) !== null) {
            $query->where('category_id', (int) $category);
        }

        if (($from = $this->filterValue($filters, 'from')) !== null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (($to = $this->filterValue($filters, 'to')) !== null) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /** @return array<string, list<string>> */
    public function filterRules(): array
    {
        return [
            'state' => ['nullable', 'string', 'in:'.implode(',', array_column(ExpenseState::cases(), 'value'))],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{key: string, label: string}>
     */
    public function activeFilterLabels(array $filters): array
    {
        $labels = [];

        if (($state = $this->filterValue($filters, 'state')) !== null) {
            $labels[] = ['key' => 'state', 'label' => 'État : '.$state];
        }

        if (($category = $this->filterValue($filters, 'category_id')) !== null) {
            $labels[] = ['key' => 'category_id', 'label' => "Catégorie n° {$category}"];
        }

        if (($from = $this->filterValue($filters, 'from')) !== null) {
            $labels[] = ['key' => 'from', 'label' => "À partir du {$from}"];
        }

        if (($to = $this->filterValue($filters, 'to')) !== null) {
            $labels[] = ['key' => 'to', 'label' => "Jusqu'au {$to}"];
        }

        return $labels;
    }

    /** @return list<string> */
    public function sortableColumns(): array
    {
        return ['created_at', 'requested_amount', 'state'];
    }

    public function defaultSort(): string
    {
        return 'created_at';
    }

    /** @return list<string> */
    public function csvHeaders(): array
    {
        return ['Identifiant', 'Motif', 'Montant (XOF)', 'État', 'Catégorie', 'Demandeur', 'Créée le'];
    }

    /** @return list<string|int|null> */
    public function csvRow(Model $record): array
    {
        if (! $record instanceof Expense) {
            throw new LogicException('La liste des dépenses ne peut exporter que des dépenses.');
        }

        return [
            (int) $record->getKey(),
            (string) $record->reason,
            // Entier brut : additionnable dans un tableur, jamais « 12 000 F CFA ».
            (int) $record->requested_amount,
            $record->state->value,
            $record->category?->name,
            $record->requester->person->full_name,
            $record->created_at->setTimezone('Africa/Niamey')->format('Y-m-d H:i'),
        ];
    }
}
