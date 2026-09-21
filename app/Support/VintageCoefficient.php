<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Barème de millésime du registre des parts de contribution.
 *
 * Calcul pur : il ne lit que sa configuration et la date de l'événement. Le service qui l'appelle
 * recopie le millésime et le coefficient sur la ligne émise, de sorte qu'une évolution ultérieure
 * du barème ne puisse rien réécrire.
 */
final readonly class VintageCoefficient
{
    public const NEUTRAL_BASIS_POINTS = 10_000;

    /** @param array<int, int> $basisPointsByYear Millésime (1 = première année) → points de base. */
    public function __construct(
        private CarbonImmutable $activityStartedOn,
        private array $basisPointsByYear,
        private int $defaultBasisPoints,
    ) {
        if ($this->defaultBasisPoints < 1) {
            throw new InvalidArgumentException('Le coefficient par défaut doit être un entier positif de points de base.');
        }

        foreach ($this->basisPointsByYear as $year => $basisPoints) {
            if ($year < 1 || $basisPoints < 1) {
                throw new InvalidArgumentException('Chaque millésime du barème doit porter un coefficient entier positif.');
            }
        }
    }

    public static function fromConfiguration(): self
    {
        $startedOn = config('contribution-shares.activity_started_on');
        $coefficients = config('contribution-shares.coefficients');
        $default = config('contribution-shares.default_coefficient');

        if (! is_string($startedOn) || ! is_array($coefficients) || ! is_int($default)) {
            throw new InvalidArgumentException('La configuration du barème de millésime est incomplète.');
        }

        /** @var array<int, int> $byYear */
        $byYear = [];

        foreach ($coefficients as $year => $basisPoints) {
            $byYear[(int) $year] = (int) $basisPoints;
        }

        return new self(CarbonImmutable::parse($startedOn)->startOfDay(), $byYear, $default);
    }

    /**
     * Millésime et coefficient applicables à la date de l'événement.
     *
     * @return array{year: int, basis_points: int}
     */
    public function at(CarbonImmutable $occurredOn): array
    {
        $year = $this->year($occurredOn);

        return [
            'year' => $year,
            'basis_points' => $this->basisPointsByYear[$year] ?? $this->defaultBasisPoints,
        ];
    }

    /**
     * Parts émises pour un montant, en division entière : tout reliquat est perdu, comme partout
     * ailleurs dans le livre financier.
     */
    public function shares(int $amount, int $basisPoints): int
    {
        if ($basisPoints < 1) {
            throw new InvalidArgumentException('Le coefficient stocké doit être un entier positif de points de base.');
        }

        return intdiv(Money::from($amount)->value() * $basisPoints, self::NEUTRAL_BASIS_POINTS);
    }

    /** Libellé lisible d'un coefficient, sans zéro inutile : « × 1,5 » plutôt que « × 1,50 ». */
    public function label(int $basisPoints): string
    {
        return '× '.str_replace('.', ',', rtrim(rtrim(number_format($basisPoints / self::NEUTRAL_BASIS_POINTS, 2, '.', ''), '0'), '.'));
    }

    private function year(CarbonImmutable $occurredOn): int
    {
        $date = $occurredOn->startOfDay();

        if ($date->lessThan($this->activityStartedOn)) {
            return 1;
        }

        // Comptage par bornes successives plutôt que par différence flottante : la frontière du
        // 31 août doit tomber exactement, années bissextiles comprises.
        $year = 1;
        $boundary = $this->activityStartedOn->addYear();

        while ($date->greaterThanOrEqualTo($boundary)) {
            $year++;
            $boundary = $boundary->addYear();
        }

        return $year;
    }
}
