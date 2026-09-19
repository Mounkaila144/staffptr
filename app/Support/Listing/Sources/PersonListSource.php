<?php

namespace App\Support\Listing\Sources;

use App\Enums\PersonOperationalStatus;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Support\Listing\AbstractListSource;
use App\Support\Listing\ListRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Liste principale « Personnes » (module `Identity`).
 *
 * `Person::visibleTo()` limite un responsable à son équipe et chacun à sa propre fiche. L'export
 * ne porte que l'identité et le statut opérationnel : ni téléphone, ni document, ni donnée
 * sensible qui n'a rien à faire dans un fichier qui circule.
 */
final class PersonListSource extends AbstractListSource
{
    public function key(): string
    {
        return ListRegistry::PEOPLE;
    }

    public function label(): string
    {
        return 'Personnes';
    }

    public function permission(): string
    {
        return 'fiche.consulter';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Person>
     */
    public function query(User $viewer, array $filters): Builder
    {
        $query = Person::query()->visibleTo($viewer);

        if (($status = $this->filterValue($filters, 'status')) !== null) {
            $query->where('operational_status', $status);
        }

        if (($from = $this->filterValue($filters, 'from')) !== null) {
            $query->whereDate('first_seen_at', '>=', $from);
        }

        if (($to = $this->filterValue($filters, 'to')) !== null) {
            $query->whereDate('first_seen_at', '<=', $to);
        }

        return $query;
    }

    /** @return array<string, list<string>> */
    public function filterRules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(PersonOperationalStatus::cases(), 'value'))],
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

        if (($status = $this->filterValue($filters, 'status')) !== null) {
            $labels[] = [
                'key' => 'status',
                'label' => 'Statut : '.(PersonOperationalStatus::tryFrom($status)?->label() ?? $status),
            ];
        }

        if (($from = $this->filterValue($filters, 'from')) !== null) {
            $labels[] = ['key' => 'from', 'label' => "Entrée à partir du {$from}"];
        }

        if (($to = $this->filterValue($filters, 'to')) !== null) {
            $labels[] = ['key' => 'to', 'label' => "Entrée jusqu'au {$to}"];
        }

        return $labels;
    }

    /** @return list<string> */
    public function sortableColumns(): array
    {
        return ['full_name', 'first_seen_at', 'operational_status'];
    }

    public function defaultSort(): string
    {
        return 'full_name';
    }

    /** @return list<string> */
    public function csvHeaders(): array
    {
        return ['Identifiant', 'Nom complet', 'Statut opérationnel', 'Première présence'];
    }

    /** @return list<string|int|null> */
    public function csvRow(Model $record): array
    {
        if (! $record instanceof Person) {
            throw new LogicException('La liste des personnes ne peut exporter que des personnes.');
        }

        return [
            (int) $record->getKey(),
            (string) $record->full_name,
            $record->operational_status->value,
            $record->first_seen_at->toDateString(),
        ];
    }
}
