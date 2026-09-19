<?php

namespace App\Services\Finance;

use App\Enums\AlertLevel;
use Carbon\CarbonImmutable;

/**
 * Résultat complet d'une évaluation d'alerte : le niveau, mais aussi la méthode et la date des
 * données source, parce que l'AC 6 interdit d'afficher un niveau sans dire d'où il vient.
 *
 * `frozen` distingue un niveau recalculé d'un niveau figé à la clôture mensuelle : un mois clos
 * n'est jamais recalculé rétroactivement (AC 5).
 */
final class AlertLevelAssessment
{
    public function __construct(
        public readonly AlertLevel $level,
        public readonly CarbonImmutable $month,
        public readonly int $baseline,
        public readonly int $collections,
        public readonly int $previousCollections,
        public readonly string $method,
        public readonly CarbonImmutable $sourceDate,
        public readonly bool $frozen,
    ) {}

    /**
     * Forme destinée aux props Inertia. Le libellé textuel accompagne toujours le niveau : aucune
     * information n'est portée par la couleur seule (AC 4, NFR31).
     *
     * @return array{level: string, level_label: string, month: string, month_label: string, baseline: int, collections: int, previous_collections: int, method: string, source_date: string, frozen: bool}
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level->value,
            'level_label' => $this->level->label(),
            'month' => $this->month->toDateString(),
            'month_label' => $this->monthLabel(),
            'baseline' => $this->baseline,
            'collections' => $this->collections,
            'previous_collections' => $this->previousCollections,
            'method' => $this->method,
            'source_date' => $this->sourceDate->toDateString(),
            'frozen' => $this->frozen,
        ];
    }

    public function monthLabel(): string
    {
        $months = [
            1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
        ];

        return $months[(int) $this->month->format('n')].' '.$this->month->format('Y');
    }
}
