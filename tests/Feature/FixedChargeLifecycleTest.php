<?php

namespace Tests\Feature;

use App\Models\Finance\Contract;
use App\Models\Finance\Expense;
use App\Models\Finance\FixedCharge;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Finance\FixedChargeService;
use Database\Seeders\SettingSeeder;
use Tests\Support\IdentityTestCase;

class FixedChargeLifecycleTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_ac_8_10_64_and_70_only_active_dynamic_charges_feed_the_base_and_reserve_objective(): void
    {
        FixedCharge::factory()->create(['monthly_amount' => 100_000, 'is_active' => true]);
        FixedCharge::factory()->create(['monthly_amount' => 250_000, 'is_active' => true]);
        FixedCharge::factory()->create(['monthly_amount' => 900_000, 'is_active' => false]);
        $service = app(FixedChargeService::class);

        $this->assertSame(350_000, $service->currentBaseAmount());
        $this->assertSame(1_050_000, $service->reserveObjectiveAmount());

        FixedCharge::factory()->create([
            'label' => 'Connexion satellite dynamique',
            'monthly_amount' => 50_000,
            'is_active' => true,
        ]);

        $this->assertSame(400_000, $service->currentBaseAmount());
        $this->assertSame(1_200_000, $service->reserveObjectiveAmount());
    }

    public function test_ac_9_and_77_preview_quantifies_the_impact_before_persistence(): void
    {
        FixedCharge::factory()->create(['monthly_amount' => 100_000, 'is_active' => true]);
        $service = app(FixedChargeService::class);

        $preview = $service->preview(null, 50_000, true);

        $this->assertSame(100_000, $preview['current_base_amount']);
        $this->assertSame(150_000, $preview['proposed_base_amount']);
        $this->assertSame(300_000, $preview['current_objective_amount']);
        $this->assertSame(450_000, $preview['proposed_objective_amount']);
        $this->assertSame(150_000, $preview['delta_amount']);
        $this->assertDatabaseCount('fixed_charges', 1);
    }

    public function test_ac_11_project_direct_costs_never_enter_the_fixed_charge_base(): void
    {
        FixedCharge::factory()->create(['monthly_amount' => 80_000, 'is_active' => true]);
        $contract = Contract::factory()->create();
        Expense::factory()->create([
            'contract_id' => $contract->getKey(),
            'requested_amount' => 5_000_000,
        ]);

        $this->assertSame(80_000, app(FixedChargeService::class)->currentBaseAmount());
    }

    public function test_ac_12_creation_amount_and_activity_changes_are_audited(): void
    {
        $actor = User::factory()->active()->create();
        $service = app(FixedChargeService::class);
        $charge = $service->create('Sécurité des locaux', 60_000, true, $actor);
        $service->update($charge, 'Gardiennage', 75_000, false, $actor);
        $service->setActive($charge, true, $actor);

        $this->assertEqualsCanonicalizing([
            'fixed_charge_created',
            'fixed_charge_updated',
            'fixed_charge_activated',
        ], AuditLog::query()
            ->where('auditable_type', FixedCharge::class)
            ->where('auditable_id', $charge->getKey())
            ->pluck('action')
            ->all());
    }
}
