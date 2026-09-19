<?php

namespace Tests\Feature;

use App\Enums\AlertLevel;
use App\Enums\ExpenseState;
use App\Enums\UserState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\Payment;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Finance\AlertLevelExpenseNotice;
use App\Services\Finance\AlertLevelService;
use App\Services\Finance\ExpenseApprovalQueueService;
use App\Services\Finance\ExpenseApprovalService;
use App\Services\Finance\ShareEntitlementService;
use App\Services\Identity\IdentityService;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Story 9.1, Task 3 — effets **bornés** du niveau d'alerte (AC 8 à 13, AC 40, AC 41).
 *
 * Le sujet de ce fichier est autant ce que l'alerte fait que ce qu'elle ne fait **pas**. Le rouge
 * bloque une seule écriture dans tout le produit — l'activation d'un nouveau compte — et rien
 * d'autre : ni un versement de part, ni une approbation de dépense, ni une personne.
 */
class AlertLevelEffectsTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->month = CarbonImmutable::now('Africa/Niamey')->startOfMonth();
    }

    /**
     * AC 8, AC 41 — en rouge, l'activation d'un **nouveau** compte employé est refusée côté
     * serveur, et le message **nomme le niveau d'alerte**. Ferme la dépendance avant de 7.3 AC 5.
     */
    public function test_ac_8_activating_a_new_employee_account_is_refused_in_red(): void
    {
        $this->putCompanyInRed();
        $direction = User::factory()->active()->withRole('direction')->create();
        $candidate = User::factory()->withRole('employe')->create(['state' => UserState::Invite]);

        try {
            app(IdentityService::class)->changeUserState(
                user: $candidate,
                state: UserState::Actif,
                actorId: $direction->getKey(),
                actorLabel: 'Direction',
                reason: 'Activation du compte.',
            );
            $this->fail("L'activation aurait dû être refusée par le niveau rouge.");
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Rouge', $exception->errors()['state'][0]);
        }

        $this->assertSame(UserState::Invite, $candidate->fresh()->state);
    }

    /** AC 8 — le refus vaut aussi pour un nouveau compte stagiaire. */
    public function test_ac_8_activating_a_new_intern_account_is_refused_in_red(): void
    {
        $this->putCompanyInRed();
        $direction = User::factory()->active()->withRole('direction')->create();
        $candidate = User::factory()->withRole('stagiaire')->create(['state' => UserState::Invite]);

        $this->expectException(ValidationException::class);
        app(IdentityService::class)->changeUserState(
            user: $candidate,
            state: UserState::Actif,
            actorId: $direction->getKey(),
            actorLabel: 'Direction',
            reason: 'Activation du compte.',
        );
    }

    /** AC 13 — le refus produit une entrée d'audit nommant le niveau et le compte concerné. */
    public function test_ac_13_the_refusal_is_audited_with_the_level_and_the_account(): void
    {
        $this->putCompanyInRed();
        $direction = User::factory()->active()->withRole('direction')->create();
        $candidate = User::factory()->withRole('employe')->create(['state' => UserState::Invite]);

        try {
            app(IdentityService::class)->changeUserState(
                user: $candidate,
                state: UserState::Actif,
                actorId: $direction->getKey(),
                actorLabel: 'Direction',
                reason: 'Activation du compte.',
            );
        } catch (ValidationException) {
            // Le refus est attendu ; c'est sa trace qui est vérifiée ici.
        }

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $candidate->getKey(),
            'action' => 'account_activation_refused_by_alert',
        ]);
        $audit = AuditLog::query()
            ->where('action', 'account_activation_refused_by_alert')
            ->firstOrFail();
        $this->assertSame('rouge', $audit->new_values['alert_level']);
    }

    /**
     * AC 12, RM-18, P3 — **aucune personne n'est jamais bloquée**. La réactivation d'un compte
     * suspendu reste possible en rouge : le niveau d'alerte peut refuser une écriture nouvelle,
     * il ne peut pas retenir quelqu'un dehors.
     */
    public function test_ac_12_reactivating_a_suspended_account_remains_possible_in_red(): void
    {
        $this->putCompanyInRed();
        $direction = User::factory()->active()->withRole('direction')->create();
        $suspended = User::factory()->withRole('employe')->create(['state' => UserState::Suspendu]);

        $reactivated = app(IdentityService::class)->changeUserState(
            user: $suspended,
            state: UserState::Actif,
            actorId: $direction->getKey(),
            actorLabel: 'Direction',
            reason: 'Retour de la personne.',
        );

        $this->assertSame(UserState::Actif, $reactivated->state);
    }

    /**
     * AC 12 — le niveau rouge ne modifie **aucun** état, rôle, permission ni session d'une
     * personne déjà active. Le recalcul de l'alerte est une lecture, pas une action sur les gens.
     */
    public function test_ac_12_a_red_alert_changes_no_state_role_permission_or_session(): void
    {
        $active = User::factory()->active()->withRole('employe')->create();
        $before = [
            'state' => $active->state,
            'roles' => $active->getRoleNames()->sort()->values()->all(),
            'permissions' => $active->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ];
        $sessionCount = DB::table('sessions')->count();

        $this->putCompanyInRed();
        $this->artisan('ptr:recalculate-alert-level')->assertSuccessful();

        $after = $active->fresh();
        $this->assertSame(AlertLevel::Rouge, app(AlertLevelService::class)->current());
        $this->assertSame($before['state'], $after->state);
        $this->assertSame($before['roles'], $after->getRoleNames()->sort()->values()->all());
        $this->assertSame($before['permissions'], $after->getAllPermissions()->pluck('name')->sort()->values()->all());
        $this->assertSame($sessionCount, DB::table('sessions')->count());
    }

    /**
     * AC 9 — en rouge, une dépense de catégorie non essentielle porte un avertissement explicite
     * **et reste approuvable**. L'avertissement n'éteint pas `can_decide`.
     */
    public function test_ac_9_a_non_essential_expense_warns_but_stays_approvable_in_red(): void
    {
        $this->seed(ExpenseCategorySeeder::class);
        $this->putCompanyInRed();
        $expense = $this->requestedExpense(essential: false);
        // La double approbation exige exactement deux comptes de direction (règle de la story 4.x,
        // indépendante de l'alerte). Sans eux, `can_decide` serait faux pour une raison qui n'a
        // rien à voir avec le niveau rouge, et le test ne prouverait rien.
        $approver = $this->approvalReadyDirection();

        $item = app(ExpenseApprovalQueueService::class)->decisionItem(
            app(ExpenseApprovalQueueService::class)->loadForDecision($expense),
            $approver,
        );

        $this->assertNotNull($item['alert_warning']);
        $this->assertStringContainsString('Rouge', $item['alert_warning']);
        $this->assertStringContainsString("L'approbation reste possible", $item['alert_warning']);
        $this->assertTrue($item['can_decide'], "L'avertissement ne doit jamais bloquer la décision.");
    }

    /** AC 9 — une catégorie marquée « essentielle » ne déclenche aucun avertissement. */
    public function test_ac_9_an_essential_category_raises_no_warning_in_red(): void
    {
        $this->seed(ExpenseCategorySeeder::class);
        $this->putCompanyInRed();
        $expense = $this->requestedExpense(essential: true);

        $this->assertNull(app(AlertLevelExpenseNotice::class)->forExpense($expense));
    }

    /** AC 9 — hors du rouge, aucun avertissement n'est affiché, même sur une catégorie non essentielle. */
    public function test_ac_9_no_warning_is_shown_outside_the_red_level(): void
    {
        $this->seed(ExpenseCategorySeeder::class);
        $expense = $this->requestedExpense(essential: false);

        $this->assertSame(AlertLevel::Vert, app(AlertLevelService::class)->current());
        $this->assertNull(app(AlertLevelExpenseNotice::class)->forExpense($expense));
    }

    /** AC 9, AC 13 — l'approbation aboutit malgré le rouge, et l'audit nomme le niveau. */
    public function test_ac_13_approving_under_red_succeeds_and_records_the_level(): void
    {
        $this->seed(ExpenseCategorySeeder::class);
        $this->putCompanyInRed();
        $expense = $this->requestedExpense(essential: false);
        $approver = $this->approvalReadyDirection();

        $decided = app(ExpenseApprovalService::class)->approve($expense, $approver);

        $this->assertContains($decided->state, [ExpenseState::Demandee, ExpenseState::Approuvee]);
        $audit = AuditLog::query()
            ->where('action', 'expense_approved')
            ->where('auditable_id', $expense->getKey())
            ->firstOrFail();
        $this->assertSame('rouge', $audit->new_values['alert_level']);
        $this->assertTrue($audit->new_values['alert_warning_shown']);
    }

    /**
     * **Test bloquant no 13 — « parts 10 %/30 % payables en rouge »** (AC 10, AC 40, RM-14,
     * FR165, CONTRA-07).
     *
     * Une part est une créance acquise sur un encaissement déjà réalisé. La retenir reviendrait à
     * faire porter la tension de trésorerie de l'entreprise à une personne, ce que le produit
     * s'interdit. Ce test échoue dès qu'une garde d'alerte est introduite dans
     * `ShareEntitlementService`, et son échec bloque la porte qualité du Jalon 4.
     */
    public function test_blocking_rule_13_shares_of_ten_and_thirty_percent_remain_payable_in_red(): void
    {
        $this->seed(ExpenseCategorySeeder::class);
        $this->putCompanyInRed();
        $this->assertSame(AlertLevel::Rouge, app(AlertLevelService::class)->current());

        $beneficiary = User::factory()->active()->withRole('employe')->create();
        $entitlement = ShareEntitlement::factory()->create([
            'beneficiary_id' => $beneficiary->getKey(),
            'share_amount' => 300_000,
            'paid_amount' => 0,
            'reversed_at' => null,
        ]);

        // Le calcul reste entier : le montant à verser n'est pas rogné par le niveau d'alerte.
        $this->assertSame(300_000, $entitlement->remainingAmount());

        // Et le versement reste demandable : aucune exception, une dépense est bien créée.
        $expense = app(ShareEntitlementService::class)->requestPayment($entitlement, $beneficiary);

        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertSame(300_000, (int) $expense->requested_amount);
        $this->assertSame(ExpenseState::Demandee, $expense->state);
        $this->assertSame($entitlement->getKey(), (int) $expense->share_entitlement_id);
    }

    /**
     * Met l'entreprise en rouge par les données seules : deux mois consécutifs d'encaissements
     * sous l'assiette des charges fixes. Aucun niveau n'est jamais saisi.
     */
    private function putCompanyInRed(): void
    {
        FixedCharge::factory()->create(['monthly_amount' => 2_000_000, 'is_active' => true]);
        Payment::factory()->create([
            'received_amount' => 100_000,
            'received_on' => $this->month->addDays(2)->toDateString(),
        ]);
        Payment::factory()->create([
            'received_amount' => 100_000,
            'received_on' => $this->month->subMonth()->addDays(2)->toDateString(),
        ]);
    }

    /**
     * Crée les deux comptes de direction attendus par la double approbation et retourne celui qui
     * décidera. C'est un prérequis de la story 4.x, pas un effet de l'alerte.
     */
    private function approvalReadyDirection(): User
    {
        $approver = User::factory()->active()->withRole('direction')->create();
        User::factory()->active()->withRole('direction')->create();

        return $approver;
    }

    private function requestedExpense(bool $essential): Expense
    {
        $category = ExpenseCategory::query()->where('is_essential', $essential)->firstOrFail();

        return Expense::factory()->create([
            'category_id' => $category->getKey(),
            'state' => ExpenseState::Demandee->value,
        ]);
    }
}
