<?php

namespace Tests\Feature;

use App\Enums\CorrectionPlanState;
use App\Models\Finance\CorrectionPlan;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Notifications\CorrectionPlanReminderNotification;
use App\Services\Finance\AlertLevelService;
use App\Services\Finance\CorrectionPlanReminderService;
use App\Services\Finance\CorrectionPlanService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Story 9.1, Task 4 — plan correctif du niveau orange (AC 11, AC 14 à 18).
 */
class CorrectionPlanTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    private CarbonImmutable $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        // `Queue::fake()` laisse l'écriture `database` s'effectuer — c'est elle qui fait foi — et
        // intercepte la mise en file WhatsApp, qui ne doit jamais atteindre le réseau en test.
        Queue::fake();
        $this->month = CarbonImmutable::now('Africa/Niamey')->startOfMonth();
    }

    /** AC 14 — le plan porte constat, actions, responsables, échéance et résultat attendu. */
    public function test_ac_14_a_plan_carries_its_five_required_fields(): void
    {
        $direction = $this->direction();

        $plan = app(CorrectionPlanService::class)->create($this->month, $this->planData(), $direction);

        $this->assertSame('Les encaissements sont restés sous l’assiette.', $plan->finding);
        $this->assertSame('Relancer les factures échues.', $plan->actions);
        $this->assertSame('Direction et responsable financier', $plan->responsibles);
        $this->assertSame($this->month->addDays(14)->toDateString(), $plan->due_on->toDateString());
        $this->assertSame('Retour au niveau de l’assiette.', $plan->expected_result);
        $this->assertSame(CorrectionPlanState::Brouillon, $plan->state);
        $this->assertSame(1, $plan->version);
    }

    /** AC 15 — le plan est rattaché au mois qui a déclenché l'orange et reste consultable ensuite. */
    public function test_ac_15_the_plan_is_attached_to_its_month_and_stays_readable(): void
    {
        $direction = $this->direction();
        app(CorrectionPlanService::class)->create($this->month, $this->planData(), $direction);

        // On revient au vert : le plan doit rester consultable malgré tout.
        $this->assertNotNull(app(CorrectionPlanService::class)->currentFor($this->month));
        $this->assertNull(app(CorrectionPlanService::class)->currentFor($this->month->addMonth()));
    }

    /** AC 18 — création et validation produisent chacune une entrée d'audit. */
    public function test_ac_18_creation_and_validation_are_audited(): void
    {
        $direction = $this->direction();
        $plan = app(CorrectionPlanService::class)->create($this->month, $this->planData(), $direction);
        app(CorrectionPlanService::class)->validate($plan, $direction);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $plan->getKey(),
            'action' => 'correction_plan_created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $plan->getKey(),
            'action' => 'correction_plan_validated',
        ]);
    }

    /** AC 17 — un plan validé ne se valide pas deux fois et ne se modifie plus. */
    public function test_ac_17_a_validated_plan_cannot_be_validated_again(): void
    {
        $direction = $this->direction();
        $plan = app(CorrectionPlanService::class)->create($this->month, $this->planData(), $direction);
        $validated = app(CorrectionPlanService::class)->validate($plan, $direction);

        $this->assertTrue($validated->state->isFrozen());
        $this->expectException(ValidationException::class);
        app(CorrectionPlanService::class)->validate($validated, $direction);
    }

    /**
     * AC 17 — une révision crée une **nouvelle version liée**, sans jamais réécrire l'original.
     * L'ancienne version reste lisible avec ses valeurs d'origine.
     */
    public function test_ac_17_a_revision_creates_a_new_linked_version(): void
    {
        $direction = $this->direction();
        $plan = app(CorrectionPlanService::class)->create($this->month, $this->planData(), $direction);
        $validated = app(CorrectionPlanService::class)->validate($plan, $direction);

        $revision = app(CorrectionPlanService::class)->revise($validated, [
            ...$this->planData(),
            'actions' => 'Suspendre les engagements non essentiels.',
            'revision_reason' => 'Les relances de factures n’ont pas suffi.',
        ], $direction);

        $this->assertSame(2, $revision->version);
        $this->assertSame($validated->getKey(), $revision->previous_id);
        $this->assertSame(CorrectionPlanState::Brouillon, $revision->state);

        // L'original est intact.
        $original = $validated->fresh();
        $this->assertSame('Relancer les factures échues.', $original->actions);
        $this->assertSame(CorrectionPlanState::Valide, $original->state);
        $this->assertSame(2, CorrectionPlan::query()->forMonth($this->month)->count());
        $this->assertSame(2, app(CorrectionPlanService::class)->currentFor($this->month)->version);
    }

    /** AC 17 — un plan encore en brouillon se corrige, il ne se révise pas. */
    public function test_ac_17_a_draft_plan_cannot_be_revised(): void
    {
        $direction = $this->direction();
        $plan = app(CorrectionPlanService::class)->create($this->month, $this->planData(), $direction);

        $this->expectException(ValidationException::class);
        app(CorrectionPlanService::class)->revise($plan, [
            ...$this->planData(),
            'revision_reason' => 'Tentative de révision prématurée.',
        ], $direction);
    }

    /** SOC-03 — aucune suppression physique, garantie jusque dans le déclencheur de base. */
    public function test_soc_3_a_correction_plan_cannot_be_deleted(): void
    {
        $plan = CorrectionPlan::factory()->create(['created_by' => $this->direction()->getKey()]);

        $this->expectException(\LogicException::class);
        $plan->delete();
    }

    /** AC 11 — en orange sans plan, `direction` est relancée. */
    public function test_ac_11_direction_is_reminded_while_no_plan_exists(): void
    {
        $this->putCompanyInOrange();
        $direction = $this->direction();

        $sent = app(CorrectionPlanReminderService::class)->dispatchDue();

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $direction->getKey(),
            'type' => CorrectionPlanReminderNotification::class,
        ]);
    }

    /** AC 13 — la relance est auditée en nommant le niveau et le mois. */
    public function test_ac_13_the_reminder_is_audited_with_the_level(): void
    {
        $this->putCompanyInOrange();
        $direction = $this->direction();

        app(CorrectionPlanReminderService::class)->dispatchDue();

        $audit = AuditLog::query()
            ->where('action', 'correction_plan_reminder_sent')
            ->where('auditable_id', $direction->getKey())
            ->firstOrFail();
        $this->assertSame('orange', $audit->new_values['alert_level']);
        $this->assertSame($this->month->toDateString(), $audit->new_values['month']);
    }

    /** AC 16 — la relance cesse dès l'enregistrement du plan. */
    public function test_ac_16_reminders_stop_as_soon_as_the_plan_exists(): void
    {
        $this->putCompanyInOrange();
        $direction = $this->direction();

        $this->assertSame(1, app(CorrectionPlanReminderService::class)->dispatchDue());

        app(CorrectionPlanService::class)->create($this->month, $this->planData(), $direction);

        // Le lendemain, plus rien : le plan existe.
        $this->travel(1)->day();
        $this->assertSame(0, app(CorrectionPlanReminderService::class)->dispatchDue());
    }

    /** AC 35 — la relance est idempotente : rejouée le même jour, elle ne double pas. */
    public function test_ac_35_replaying_the_reminder_the_same_day_creates_no_duplicate(): void
    {
        $this->putCompanyInOrange();
        $this->direction();

        app(CorrectionPlanReminderService::class)->dispatchDue();
        app(CorrectionPlanReminderService::class)->dispatchDue();

        $this->assertSame(1, DatabaseNotification::query()->count());
    }

    /** Un nouveau jour relance, tant que le plan manque : l'exigence ne s'oublie pas. */
    public function test_a_new_day_produces_a_new_reminder_while_the_plan_is_missing(): void
    {
        $this->putCompanyInOrange();
        $this->direction();

        app(CorrectionPlanReminderService::class)->dispatchDue();
        $this->travel(1)->day();
        app(CorrectionPlanReminderService::class)->dispatchDue();

        $this->assertSame(2, DatabaseNotification::query()->count());
    }

    /** AC 11 — l'échéance des 48 heures court depuis le passage effectif en orange. */
    public function test_ac_11_the_deadline_runs_48_hours_from_the_observed_orange(): void
    {
        $this->putCompanyInOrange();
        $this->direction();
        $this->artisan('ptr:recalculate-alert-level')->assertSuccessful();

        $state = app(AlertLevelService::class)->state($this->month);
        $dueAt = app(CorrectionPlanReminderService::class)->dueAt(
            $this->month->toDateString(),
            CarbonImmutable::now('Africa/Niamey'),
        );

        $this->assertNotNull($state);
        $this->assertSame(
            $state->observed_at->addHours(48)->setTimezone('Africa/Niamey')->toISOString(),
            $dueAt->toISOString(),
        );
    }

    /** Le niveau vert ne réclame aucun plan : l'exigence est propre à l'orange. */
    public function test_no_reminder_is_sent_outside_the_orange_level(): void
    {
        $this->direction();

        $this->assertSame(0, app(CorrectionPlanReminderService::class)->dispatchDue());
    }

    private function direction(): User
    {
        return User::factory()->active()->withRole('direction')->create();
    }

    /** Un seul mois sous l'assiette : le mois précédent l'atteint. */
    private function putCompanyInOrange(): void
    {
        FixedCharge::factory()->create(['monthly_amount' => 1_000_000, 'is_active' => true]);
        Payment::factory()->create([
            'received_amount' => 200_000,
            'received_on' => $this->month->addDays(2)->toDateString(),
        ]);
        Payment::factory()->create([
            'received_amount' => 1_500_000,
            'received_on' => $this->month->subMonth()->addDays(2)->toDateString(),
        ]);
    }

    /** @return array{finding: string, actions: string, responsibles: string, due_on: string, expected_result: string} */
    private function planData(): array
    {
        return [
            'finding' => 'Les encaissements sont restés sous l’assiette.',
            'actions' => 'Relancer les factures échues.',
            'responsibles' => 'Direction et responsable financier',
            'due_on' => $this->month->addDays(14)->toDateString(),
            'expected_result' => 'Retour au niveau de l’assiette.',
        ];
    }
}
