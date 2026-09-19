<?php

namespace Tests\Unit;

use App\Enums\AlertLevel;
use App\Services\Finance\AlertLevelCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Story 9.1 — calcul pur des trois niveaux d'alerte (AC 1, AC 2, AC 39).
 *
 * Aucune base, aucun cache : c'est ce qui permet de couvrir les cas limites exactement, notamment
 * l'égalité stricte entre encaissements et assiette.
 */
class AlertLevelCalculatorTest extends TestCase
{
    private AlertLevelCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new AlertLevelCalculator;
    }

    /** AC 2 — vert : les encaissements du mois atteignent l'assiette. */
    public function test_ac_2_collections_above_the_baseline_are_green(): void
    {
        $this->assertSame(
            AlertLevel::Vert,
            $this->calculator->level(baseline: 1_000_000, collections: 1_500_000, previousCollections: 200_000),
        );
    }

    /**
     * AC 2 — cas limite : l'égalité stricte est verte. « ≥ assiette » se lit littéralement ;
     * un mois qui couvre exactement ses charges n'est pas en difficulté.
     */
    public function test_ac_2_collections_exactly_equal_to_the_baseline_are_green(): void
    {
        $this->assertSame(
            AlertLevel::Vert,
            $this->calculator->level(baseline: 1_000_000, collections: 1_000_000, previousCollections: 0),
        );
    }

    /** AC 2 — orange : un seul mois sous l'assiette. */
    public function test_ac_2_one_month_below_the_baseline_is_orange(): void
    {
        $this->assertSame(
            AlertLevel::Orange,
            $this->calculator->level(baseline: 1_000_000, collections: 999_999, previousCollections: 1_000_000),
        );
    }

    /** AC 2 — rouge : deux mois consécutifs sous l'assiette. */
    public function test_ac_2_two_consecutive_months_below_the_baseline_are_red(): void
    {
        $this->assertSame(
            AlertLevel::Rouge,
            $this->calculator->level(baseline: 1_000_000, collections: 400_000, previousCollections: 999_999),
        );
    }

    /**
     * AC 1 — une assiette nulle ne peut pas déclencher d'alerte : sans charge fixe paramétrée, il
     * n'y a rien à couvrir. C'est la conséquence directe de « aucune liste codée en dur ».
     */
    public function test_ac_1_a_zero_baseline_never_raises_an_alert(): void
    {
        $this->assertSame(
            AlertLevel::Vert,
            $this->calculator->level(baseline: 0, collections: 0, previousCollections: 0),
        );
    }

    /**
     * AC 3 — l'assiette est le seul levier : à encaissements constants, augmenter l'assiette
     * change le niveau. C'est ce qui rend l'ajout d'une charge fixe effectif.
     */
    public function test_ac_3_raising_the_baseline_changes_the_level_at_constant_collections(): void
    {
        $collections = 500_000;
        $previous = 500_000;

        $this->assertSame(
            AlertLevel::Vert,
            $this->calculator->level(baseline: 400_000, collections: $collections, previousCollections: $previous),
        );
        $this->assertSame(
            AlertLevel::Rouge,
            $this->calculator->level(baseline: 600_000, collections: $collections, previousCollections: $previous),
        );
    }

    /** AC 6 — la méthode nomme l'assiette, les encaissements comparés et la règle appliquée. */
    public function test_ac_6_the_method_states_the_baseline_and_the_rule(): void
    {
        $method = $this->calculator->method(1_000_000, 400_000, 900_000);

        $this->assertStringContainsString('charges fixes actives du paramétrage', $method);
        $this->assertStringContainsString('deux mois consécutifs', $method);
        $this->assertStringContainsString('1 000 000', $method);
        $this->assertStringContainsString('400 000', $method);
    }

    /** Les montants XOF sont des entiers positifs : un négatif est une erreur de programmation. */
    public function test_a_negative_amount_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->level(baseline: -1, collections: 0, previousCollections: 0);
    }
}
