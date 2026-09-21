<?php

namespace Tests\Unit;

use App\Support\VintageCoefficient;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Barème de millésime du registre des parts (story 12.1, AC 12, AC 13, AC 15).
 *
 * Calcul pur : aucune base de données, aucun conteneur.
 */
class VintageCoefficientTest extends TestCase
{
    public function test_ac_12_each_vintage_year_carries_the_coefficient_decided_by_the_direction(): void
    {
        $vintage = $this->barème();

        $this->assertSame(['year' => 1, 'basis_points' => 20_000], $vintage->at(CarbonImmutable::parse('2026-09-01')));
        $this->assertSame(['year' => 1, 'basis_points' => 20_000], $vintage->at(CarbonImmutable::parse('2027-08-31')));
        $this->assertSame(['year' => 2, 'basis_points' => 15_000], $vintage->at(CarbonImmutable::parse('2027-09-01')));
        $this->assertSame(['year' => 3, 'basis_points' => 12_500], $vintage->at(CarbonImmutable::parse('2028-09-01')));
        $this->assertSame(['year' => 4, 'basis_points' => 10_000], $vintage->at(CarbonImmutable::parse('2029-09-01')));
        $this->assertSame(['year' => 9, 'basis_points' => 10_000], $vintage->at(CarbonImmutable::parse('2034-09-01')));
    }

    /** Une date antérieure au démarrage relève de l'année 1 : rien n'était acquis avant non plus. */
    public function test_ac_12_a_date_before_the_start_of_activity_falls_in_the_first_vintage(): void
    {
        $this->assertSame(['year' => 1, 'basis_points' => 20_000], $this->barème()->at(CarbonImmutable::parse('2026-01-15')));
    }

    public function test_ac_13_shares_are_the_amount_times_the_coefficient_in_integer_division(): void
    {
        $vintage = $this->barème();

        $this->assertSame(4_000_000, $vintage->shares(2_000_000, 20_000));
        $this->assertSame(3_000_000, $vintage->shares(2_000_000, 15_000));
        $this->assertSame(2_500_000, $vintage->shares(2_000_000, 12_500));
        $this->assertSame(2_000_000, $vintage->shares(2_000_000, 10_000));
        // Division entière : le reliquat disparaît, comme partout dans le livre financier.
        $this->assertSame(1, $vintage->shares(1, 12_500));
        $this->assertSame(0, $vintage->shares(0, 20_000));
    }

    public function test_ac_13_a_negative_amount_or_an_absurd_coefficient_is_refused(): void
    {
        $vintage = $this->barème();

        $this->expectException(InvalidArgumentException::class);
        $vintage->shares(-1, 20_000);
    }

    public function test_ac_13_a_null_coefficient_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->barème()->shares(1_000, 0);
    }

    /**
     * AC 15 — le barème est une donnée de configuration. Un barème différent produit des parts
     * différentes **pour les émissions à venir** ; les lignes déjà écrites portent leur propre
     * coefficient et ne passent jamais par ce calcul à la lecture.
     */
    public function test_ac_15_changing_the_scale_changes_only_future_issuances(): void
    {
        $ancien = $this->barème();
        $nouveau = new VintageCoefficient(
            CarbonImmutable::parse('2026-09-01'),
            [1 => 50_000],
            10_000,
        );
        $dateAnnéeUn = CarbonImmutable::parse('2026-10-01');

        $this->assertSame(20_000, $ancien->at($dateAnnéeUn)['basis_points']);
        $this->assertSame(50_000, $nouveau->at($dateAnnéeUn)['basis_points']);
        // La ligne déjà émise garde son coefficient figé : c'est lui, et non le barème courant,
        // qui la revalorise.
        $this->assertSame(2_000_000, $nouveau->shares(1_000_000, 20_000));
    }

    public function test_coefficient_labels_stay_readable_without_useless_zeros(): void
    {
        $vintage = $this->barème();

        $this->assertSame('× 2', $vintage->label(20_000));
        $this->assertSame('× 1,5', $vintage->label(15_000));
        $this->assertSame('× 1,25', $vintage->label(12_500));
        $this->assertSame('× 1', $vintage->label(10_000));
    }

    private function barème(): VintageCoefficient
    {
        return new VintageCoefficient(
            CarbonImmutable::parse('2026-09-01'),
            [1 => 20_000, 2 => 15_000, 3 => 12_500],
            10_000,
        );
    }
}
