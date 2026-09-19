<?php

namespace Tests\Feature;

use App\Enums\AlertLevel;
use App\Models\Finance\AlertLevelState;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\MonthClosure;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\AlertLevelService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Story 9.1, Task 2 — assiette, cache, niveau figé et recalcul planifié (AC 1 à 7, AC 39).
 */
class AlertLevelServiceTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    private CarbonImmutable $month;

    protected function setUp(): void
    {
        parent::setUp();
        // Le recalcul déclenche la relance de `direction` quand le mois passe en orange : les
        // rôles doivent donc exister comme dans une installation réelle.
        $this->seed(RolePermissionSeeder::class);
        $this->month = CarbonImmutable::now('Africa/Niamey')->startOfMonth();
    }

    /**
     * AC 1, AC 39 — l'assiette est exactement la somme des charges fixes **actives** du
     * paramétrage. Une charge désactivée n'y entre pas, et aucune valeur n'est codée en dur.
     */
    public function test_ac_1_the_baseline_is_the_sum_of_active_fixed_charges_only(): void
    {
        FixedCharge::factory()->create(['monthly_amount' => 600_000, 'is_active' => true]);
        FixedCharge::factory()->create(['monthly_amount' => 400_000, 'is_active' => true]);
        FixedCharge::factory()->create(['monthly_amount' => 900_000, 'is_active' => false]);

        $this->assertSame(1_000_000, $this->service()->baseline());
    }

    /** AC 2 — vert sur un jeu de données dédié. */
    public function test_ac_2_a_month_that_covers_its_baseline_is_green(): void
    {
        $this->baseline(1_000_000);
        $this->collect($this->month, 1_200_000);

        $this->assertSame(AlertLevel::Vert, $this->service()->current());
    }

    /** AC 2 — orange : le mois est sous l'assiette, le précédent l'atteignait. */
    public function test_ac_2_a_single_month_below_the_baseline_is_orange(): void
    {
        $this->baseline(1_000_000);
        $this->collect($this->month, 400_000);
        $this->collect($this->month->subMonth(), 1_100_000);

        $this->assertSame(AlertLevel::Orange, $this->service()->current());
    }

    /** AC 2 — rouge : deux mois consécutifs sous l'assiette. */
    public function test_ac_2_two_consecutive_months_below_the_baseline_are_red(): void
    {
        $this->baseline(1_000_000);
        $this->collect($this->month, 400_000);
        $this->collect($this->month->subMonth(), 300_000);

        $this->assertSame(AlertLevel::Rouge, $this->service()->current());
    }

    /**
     * AC 3 — le test de bout en bout que la story réclame : **ajouter une charge fixe change
     * l'assiette et change effectivement le niveau au recalcul suivant** (complète 8.2 AC 4,
     * FR147). Le cache ne doit pas figer l'ancien niveau.
     */
    public function test_ac_3_adding_a_fixed_charge_changes_the_level_at_the_next_recalculation(): void
    {
        $this->baseline(500_000);
        $this->collect($this->month, 600_000);
        $this->collect($this->month->subMonth(), 600_000);

        $this->assertSame(AlertLevel::Vert, $this->service()->current());

        // Une charge fixe supplémentaire porte l'assiette au-dessus des encaissements des deux mois.
        FixedCharge::factory()->create(['monthly_amount' => 300_000, 'is_active' => true]);

        $this->assertSame(AlertLevel::Rouge, $this->service()->current());
    }

    /**
     * AC 5 — un mois clos garde le niveau figé à la clôture. Même si les charges fixes changent
     * ensuite, le niveau opposable du mois clos n'est jamais réécrit.
     */
    public function test_ac_5_a_closed_month_keeps_its_frozen_level(): void
    {
        $closed = $this->month->subMonth();
        $this->baseline(1_000_000);
        $this->collect($closed, 50_000);
        MonthClosure::factory()->create([
            'month' => $closed->toDateString(),
            'closed_by' => User::factory(),
            'frozen_alert_level' => AlertLevel::Vert->value,
        ]);

        $assessment = $this->service()->assess($closed);

        $this->assertSame(AlertLevel::Vert, $assessment->level);
        $this->assertTrue($assessment->frozen);
        $this->assertStringContainsString('figé à la clôture', $assessment->method);
    }

    /** AC 5 — un mois rouvert redevient calculable : le figement suit la clôture, pas le mois. */
    public function test_ac_5_a_reopened_month_is_calculated_again(): void
    {
        $reopened = $this->month->subMonth();
        $this->baseline(1_000_000);
        $this->collect($reopened, 50_000);
        $this->collect($reopened->subMonth(), 50_000);
        MonthClosure::factory()->create([
            'month' => $reopened->toDateString(),
            'closed_by' => User::factory(),
            'frozen_alert_level' => AlertLevel::Vert->value,
            'reopened_at' => now('UTC'),
            'reopened_by' => User::factory(),
            'reopen_reason' => 'Correction d’un encaissement.',
        ]);

        $assessment = $this->service()->assess($reopened);

        $this->assertFalse($assessment->frozen);
        $this->assertSame(AlertLevel::Rouge, $assessment->level);
    }

    /** AC 6 — le niveau affiché dit toujours sa méthode et la date de ses données source. */
    public function test_ac_6_the_assessment_carries_its_method_and_source_date(): void
    {
        $this->baseline(800_000);
        $this->collect($this->month, 900_000);

        $assessment = $this->service()->assess();
        $payload = $assessment->toArray();

        $this->assertSame('vert', $payload['level']);
        $this->assertSame('Vert', $payload['level_label']);
        $this->assertSame(800_000, $payload['baseline']);
        $this->assertSame(900_000, $payload['collections']);
        $this->assertStringContainsString('charges fixes actives', $payload['method']);
        $this->assertSame(CarbonImmutable::now('Africa/Niamey')->toDateString(), $payload['source_date']);
    }

    /**
     * AC 5, AC 35 — le recalcul planifié est idempotent : rejoué, il réécrit les mêmes valeurs,
     * ne crée pas de seconde ligne et ne repousse pas la date d'observation du niveau.
     */
    public function test_ac_5_the_scheduled_recalculation_is_idempotent(): void
    {
        $this->baseline(1_000_000);
        $this->collect($this->month, 200_000);
        $this->collect($this->month->subMonth(), 200_000);

        $this->artisan('ptr:recalculate-alert-level')->assertSuccessful();
        $first = AlertLevelState::query()->firstOrFail();

        $this->travel(2)->hours();
        $this->artisan('ptr:recalculate-alert-level')->assertSuccessful();
        $second = AlertLevelState::query()->firstOrFail();

        $this->assertSame(1, AlertLevelState::query()->count());
        $this->assertSame(AlertLevel::Rouge, $second->level);
        $this->assertSame(
            $first->observed_at->toISOString(),
            $second->observed_at->toISOString(),
            "La date d'observation ne doit pas être repoussée quand le niveau n'a pas changé.",
        );
        $this->assertTrue($second->calculated_at->greaterThan($first->calculated_at));
    }

    /** Le changement de niveau, lui, repousse bien la date d'observation : les 48 h repartent. */
    public function test_the_observation_date_moves_when_the_level_actually_changes(): void
    {
        $this->baseline(1_000_000);
        // Le mois précédent couvre son assiette : le passage à orange restera un passage à orange
        // et non à rouge, qui exigerait deux mois consécutifs sous l'assiette.
        $this->collect($this->month->subMonth(), 1_500_000);
        $current = $this->collect($this->month, 1_500_000);
        $this->artisan('ptr:recalculate-alert-level')->assertSuccessful();
        $green = AlertLevelState::query()->firstOrFail()->observed_at;

        $this->travel(3)->hours();
        $current->forceFill(['received_amount' => 100_000])->saveOrFail();
        $this->artisan('ptr:recalculate-alert-level')->assertSuccessful();
        $orange = AlertLevelState::query()->firstOrFail();

        $this->assertSame(AlertLevel::Orange, $orange->level);
        $this->assertTrue($orange->observed_at->greaterThan($green));
    }

    /** AC 5, § 19.1 — le cache est invalidé par l'encaissement qui le périme, pas par expiration. */
    public function test_recording_a_payment_invalidates_the_cached_level(): void
    {
        $this->baseline(1_000_000);
        $this->collect($this->month, 100_000);
        $this->collect($this->month->subMonth(), 100_000);

        $this->assertSame(AlertLevel::Rouge, $this->service()->current());

        $this->collect($this->month, 2_000_000);

        $this->assertSame(AlertLevel::Vert, $this->service()->current());
    }

    private function service(): AlertLevelService
    {
        return app(AlertLevelService::class);
    }

    private function baseline(int $amount): void
    {
        FixedCharge::factory()->create(['monthly_amount' => $amount, 'is_active' => true]);
    }

    private function collect(CarbonImmutable $month, int $amount): Payment
    {
        return Payment::factory()->create([
            'received_amount' => $amount,
            'received_on' => $month->startOfMonth()->addDays(3)->toDateString(),
        ]);
    }
}
