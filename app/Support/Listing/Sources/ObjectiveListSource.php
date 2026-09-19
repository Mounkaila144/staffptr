<?php

namespace App\Support\Listing\Sources;

use App\Enums\ObjectiveState;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Support\Listing\AbstractListSource;
use App\Support\Listing\ListRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Liste principale « Objectifs » (module `Work`).
 *
 * `Objective::visibleTo()` distingue trois périmètres : `direction` voit tout, un tuteur voit les
 * siens et ceux de son équipe, chacun voit les siens. L'export n'en connaît aucun détail — il
 * appelle la même méthode.
 */
final class ObjectiveListSource extends AbstractListSource
{
    public function key(): string
    {
        return ListRegistry::OBJECTIVES;
    }

    public function label(): string
    {
        return 'Objectifs';
    }

    public function permission(): string
    {
        return 'objectif_individuel.consulter';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Objective>
     */
    public function query(User $viewer, array $filters): Builder
    {
        $query = Objective::query()
            ->visibleTo($viewer)
            ->with(['owner.person:id,full_name']);

        if (($state = $this->filterValue($filters, 'state')) !== null) {
            $query->where('state', $state);
        }

        if (($from = $this->filterValue($filters, 'from')) !== null) {
            $query->whereDate('due_date', '>=', $from);
        }

        if (($to = $this->filterValue($filters, 'to')) !== null) {
            $query->whereDate('due_date', '<=', $to);
        }

        return $query;
    }

    /** @return array<string, list<string>> */
    public function filterRules(): array
    {
        return [
            'state' => ['nullable', 'string', 'in:'.implode(',', array_column(ObjectiveState::cases(), 'value'))],
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
            $labels[] = ['key' => 'state', 'label' => 'État : '.(ObjectiveState::tryFrom($state)?->label() ?? $state)];
        }

        if (($from = $this->filterValue($filters, 'from')) !== null) {
            $labels[] = ['key' => 'from', 'label' => "Échéance à partir du {$from}"];
        }

        if (($to = $this->filterValue($filters, 'to')) !== null) {
            $labels[] = ['key' => 'to', 'label' => "Échéance jusqu'au {$to}"];
        }

        return $labels;
    }

    /** @return list<string> */
    public function sortableColumns(): array
    {
        return ['due_date', 'progress', 'state'];
    }

    public function defaultSort(): string
    {
        return 'due_date';
    }

    /** @return list<string> */
    public function csvHeaders(): array
    {
        return ['Identifiant', 'Titre', 'Titulaire', 'État', 'Progression (%)', 'Échéance'];
    }

    /** @return list<string|int|null> */
    public function csvRow(Model $record): array
    {
        if (! $record instanceof Objective) {
            throw new LogicException('La liste des objectifs ne peut exporter que des objectifs.');
        }

        return [
            (int) $record->getKey(),
            (string) $record->title,
            $record->owner->person->full_name,
            $record->state->value,
            (int) $record->progress,
            $record->due_date->toDateString(),
        ];
    }
}
