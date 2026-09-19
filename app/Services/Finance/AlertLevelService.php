<?php

namespace App\Services\Finance;

use App\Enums\AlertLevel;
use App\Enums\PaymentState;
use App\Models\Finance\AlertLevelState;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\MonthClosure;
use App\Models\Finance\Payment;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Niveau d'alerte financière : assiette, encaissements, cache et niveau figé (FR161 à FR165).
 *
 * Le point de contrôle provisoire posé par la story 7.1 (AC 18), qui répondait toujours `vert`,
 * est remplacé ici par le calcul réel de l'Epic 9. La règle des trois niveaux vit dans
 * {@see AlertLevelCalculator}, testable sans base ; ce service ne fait que lui fournir des entiers
 * et mémoriser le résultat.
 *
 * Trois garanties structurent la classe :
 *
 * 1. L'assiette est **exclusivement** la somme des charges fixes actives du paramétrage : ajouter
 *    une charge fixe change l'assiette, donc le niveau au recalcul suivant (AC 1, AC 3, FR147).
 * 2. Un mois clos garde le niveau figé lors de la clôture ; il n'est jamais recalculé (AC 5).
 * 3. Le cache `alert:level:{aaaa-mm}` est invalidé à l'écriture — encaissement, charge fixe,
 *    clôture — jamais laissé à la seule expiration (architecture § 19.1).
 */
class AlertLevelService
{
    private const TIMEZONE = 'Africa/Niamey';

    /** Plafond de cinq minutes : le cache accélère, il ne fait jamais autorité (§ 19.1). */
    private const TTL_SECONDS = 300;

    public function __construct(private readonly AlertLevelCalculator $calculator) {}

    /** Niveau du mois en cours, tel qu'affiché en permanence sur le tableau de bord (AC 4). */
    public function current(): AlertLevel
    {
        return $this->assess()->level;
    }

    /**
     * Évaluation complète d'un mois : niveau, assiette, encaissements, méthode et date source.
     * Sans argument, le mois civil courant à Niamey.
     */
    public function assess(DateTimeInterface|string|null $month = null): AlertLevelAssessment
    {
        $observed = $this->normalizeMonth($month);
        $frozen = $this->frozenLevelFor($observed);

        if ($frozen instanceof AlertLevel) {
            return $this->frozenAssessment($observed, $frozen);
        }

        /** @var array{level: string, baseline: int, collections: int, previous_collections: int, source_date: string} $payload */
        $payload = Cache::remember(
            $this->cacheKey($observed),
            self::TTL_SECONDS,
            fn (): array => $this->computePayload($observed),
        );

        return $this->hydrate($observed, $payload, frozen: false);
    }

    /**
     * Recalcul explicite, sans passer par le cache : c'est ce que fait la tâche planifiée.
     *
     * L'opération est idempotente (AC 5, AC 35). Rejouée sur les mêmes données elle réécrit les
     * mêmes valeurs, et `observed_at` — la date de passage au niveau courant, qui fait courir les
     * 48 heures du plan correctif — n'est repoussée que si le niveau a réellement changé.
     */
    public function recalculate(DateTimeInterface|string|null $month = null): AlertLevelAssessment
    {
        $observed = $this->normalizeMonth($month);
        $this->forget($observed);
        $assessment = $this->assess($observed);
        $this->persistState($assessment);

        return $assessment;
    }

    /** Dernier état recalculé d'un mois, ou `null` si la tâche planifiée n'a jamais tourné. */
    public function state(DateTimeInterface|string|null $month = null): ?AlertLevelState
    {
        return AlertLevelState::query()
            ->whereDate('month', $this->normalizeMonth($month)->toDateString())
            ->first();
    }

    private function persistState(AlertLevelAssessment $assessment): AlertLevelState
    {
        $now = CarbonImmutable::now('UTC');
        // La ligne existante est retrouvée par `whereDate` et non par égalité stricte : selon le
        // moteur, `month` se relit `2026-08-01` ou `2026-08-01 00:00:00`, et un `updateOrCreate`
        // sur la valeur brute créerait un doublon au lieu de mettre à jour.
        $state = $this->state($assessment->month) ?? new AlertLevelState([
            'month' => $assessment->month->toDateString(),
        ]);
        $levelChanged = ! $state->exists || $state->level !== $assessment->level;

        $state->fill([
            'level' => $assessment->level->value,
            'baseline_amount' => $assessment->baseline,
            'collections_amount' => $assessment->collections,
            'previous_collections_amount' => $assessment->previousCollections,
            'source_date' => $assessment->sourceDate->toDateString(),
            'observed_at' => $levelChanged ? $now : $state->observed_at,
            'calculated_at' => $now,
        ])->saveOrFail();

        return $state;
    }

    /**
     * L'activation d'un nouveau compte employé ou stagiaire est-elle permise par la situation
     * financière ? Le rouge la refuse, et rien d'autre : aucune personne déjà active n'est
     * touchée, aucun versement n'est empêché (AC 8, AC 12, architecture § 16.4).
     */
    public function allowsAccountActivation(): bool
    {
        return ! $this->current()->blocksAccountActivation();
    }

    /**
     * Le niveau orange exige l'enregistrement d'un plan correctif sous 48 heures (AC 11, FR163).
     */
    public function requiresCorrectionPlan(): bool
    {
        return $this->current() === AlertLevel::Orange;
    }

    /** Invalidation à l'écriture : encaissement, charge fixe ou clôture (§ 19.1). */
    public function forget(DateTimeInterface|string|null $month = null): void
    {
        $observed = $this->normalizeMonth($month);
        Cache::forget($this->cacheKey($observed));
        // Le niveau d'un mois dépend aussi des encaissements du mois précédent : une écriture
        // rétroactive doit donc invalider le mois suivant.
        Cache::forget($this->cacheKey($observed->addMonth()));
    }

    /**
     * Assiette du mois : somme des charges fixes actives. Aucune liste codée en dur (AC 1).
     */
    public function baseline(): int
    {
        return (int) FixedCharge::query()->active()->sum('monthly_amount');
    }

    /** Encaissements validés d'un mois civil Niamey, en francs CFA entiers. */
    public function collectionsFor(CarbonImmutable $month): int
    {
        $start = $month->startOfMonth();

        return (int) Payment::query()
            ->where('state', PaymentState::Validated)
            ->whereBetween('received_on', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->sum('received_amount');
    }

    /**
     * Niveau figé d'un mois clos, s'il existe. La clôture la plus récente fait foi ; un mois
     * rouvert n'est plus figé et redevient calculable.
     */
    public function frozenLevelFor(CarbonImmutable $month): ?AlertLevel
    {
        $closure = MonthClosure::query()
            ->currentlyClosed()
            ->whereDate('month', $month->startOfMonth()->toDateString())
            ->whereNotNull('frozen_alert_level')
            ->orderByDesc('version')
            ->first();

        return $closure?->frozen_alert_level;
    }

    /** @return array{level: string, baseline: int, collections: int, previous_collections: int, source_date: string} */
    private function computePayload(CarbonImmutable $month): array
    {
        $baseline = $this->baseline();
        $collections = $this->collectionsFor($month);
        $previousCollections = $this->collectionsFor($month->subMonth());

        return [
            'level' => $this->calculator->level($baseline, $collections, $previousCollections)->value,
            'baseline' => $baseline,
            'collections' => $collections,
            'previous_collections' => $previousCollections,
            'source_date' => CarbonImmutable::now(self::TIMEZONE)->toDateString(),
        ];
    }

    /**
     * @param  array{level: string, baseline: int, collections: int, previous_collections: int, source_date: string}  $payload
     */
    private function hydrate(CarbonImmutable $month, array $payload, bool $frozen): AlertLevelAssessment
    {
        return new AlertLevelAssessment(
            level: AlertLevel::from($payload['level']),
            month: $month,
            baseline: $payload['baseline'],
            collections: $payload['collections'],
            previousCollections: $payload['previous_collections'],
            method: $this->calculator->method(
                $payload['baseline'],
                $payload['collections'],
                $payload['previous_collections'],
            ).($frozen ? ' Niveau figé à la clôture du mois : il n’est plus recalculé.' : ''),
            sourceDate: CarbonImmutable::createFromFormat('!Y-m-d', $payload['source_date'], self::TIMEZONE),
            frozen: $frozen,
        );
    }

    private function frozenAssessment(CarbonImmutable $month, AlertLevel $level): AlertLevelAssessment
    {
        $baseline = $this->baseline();

        return $this->hydrate($month, [
            'level' => $level->value,
            'baseline' => $baseline,
            'collections' => $this->collectionsFor($month),
            'previous_collections' => $this->collectionsFor($month->subMonth()),
            'source_date' => $month->endOfMonth()->toDateString(),
        ], frozen: true);
    }

    private function normalizeMonth(DateTimeInterface|string|null $month): CarbonImmutable
    {
        if ($month === null) {
            return CarbonImmutable::now(self::TIMEZONE)->startOfMonth();
        }

        return CarbonImmutable::parse($month, self::TIMEZONE)->startOfMonth();
    }

    private function cacheKey(CarbonImmutable $month): string
    {
        return 'alert:level:'.$month->format('Y-m');
    }
}
