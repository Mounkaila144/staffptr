<?php

namespace App\Support\Listing\Sources;

use App\Enums\DailyReportState;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Support\Listing\AbstractListSource;
use App\Support\Listing\ListRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Liste principale « Rapports quotidiens » (module `Accountability`).
 *
 * L'export ne contient jamais le **contenu** d'un rapport : il en donne l'auteur, la date et
 * l'état. Le produit parle de contribution et non de surveillance (SOC-10) ; un CSV du texte des
 * rapports d'une équipe serait un outil de contrôle, pas un outil de suivi.
 */
final class DailyReportListSource extends AbstractListSource
{
    public function key(): string
    {
        return ListRegistry::DAILY_REPORTS;
    }

    public function label(): string
    {
        return 'Rapports quotidiens';
    }

    public function permission(): string
    {
        return 'rapport_quotidien.consulter';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<DailyReport>
     */
    public function query(User $viewer, array $filters): Builder
    {
        $query = DailyReport::query()
            ->visibleTo($viewer)
            ->with(['author.person:id,full_name']);

        if (($state = $this->filterValue($filters, 'state')) !== null) {
            $query->where('state', $state);
        }

        if (($from = $this->filterValue($filters, 'from')) !== null) {
            $query->whereDate('report_date', '>=', $from);
        }

        if (($to = $this->filterValue($filters, 'to')) !== null) {
            $query->whereDate('report_date', '<=', $to);
        }

        return $query;
    }

    /** @return array<string, list<string>> */
    public function filterRules(): array
    {
        return [
            'state' => ['nullable', 'string', 'in:'.implode(',', array_column(DailyReportState::cases(), 'value'))],
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
            $labels[] = ['key' => 'state', 'label' => "État : {$state}"];
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
        return ['report_date', 'state'];
    }

    public function defaultSort(): string
    {
        return 'report_date';
    }

    /** @return list<string> */
    public function csvHeaders(): array
    {
        return ['Identifiant', 'Auteur', 'Date du rapport', 'État', 'Envoyé le'];
    }

    /** @return list<string|int|null> */
    public function csvRow(Model $record): array
    {
        if (! $record instanceof DailyReport) {
            throw new LogicException('La liste des rapports ne peut exporter que des rapports.');
        }

        return [
            (int) $record->getKey(),
            $record->author->person->full_name,
            $record->report_date->toDateString(),
            $record->state->value,
            $record->submitted_at?->setTimezone('Africa/Niamey')->format('Y-m-d H:i'),
        ];
    }
}
