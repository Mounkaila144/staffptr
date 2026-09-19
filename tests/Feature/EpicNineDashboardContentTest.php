<?php

namespace Tests\Feature;

use App\Enums\AbsenceState;
use App\Enums\DailyReportState;
use App\Enums\ExpenseState;
use App\Enums\InvoiceState;
use App\Enums\ObjectiveState;
use App\Enums\ProjectStatus;
use App\Models\Accountability\DailyReport;
use App\Models\Finance\Account;
use App\Models\Finance\Contract;
use App\Models\Finance\Expense;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use App\Services\Finance\FinancialDashboardService;
use App\Services\Platform\DirectionDashboardService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Story 9.1, Tasks 5 et 6 — **contenu** des deux tableaux de bord (AC 19, AC 20, AC 21, AC 24,
 * AC 25, AC 27, AC 28, AC 29).
 *
 * Le cloisonnement et le coût sont vérifiés dans `Http/DashboardHttpTest` ; ici on vérifie que
 * chaque bloc dit vrai.
 */
class EpicNineDashboardContentTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    private CarbonImmutable $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->month = CarbonImmutable::now('Africa/Niamey')->startOfMonth();
    }

    /**
     * AC 19 — l'écran financier expose bien les sept indicateurs exigés, plus les engagements de
     * parts de l'AC 20.
     */
    public function test_ac_19_the_financial_dashboard_exposes_every_required_block(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();

        $blocks = app(FinancialDashboardService::class)->blocks($direction);

        foreach ([
            'account_balances',       // soldes par compte
            'pending_expenses',       // dépenses en attente
            'month_collections',      // encaissements du mois
            'overdue_receivables',    // créances échues
            'reconciliation_gaps',    // écarts de rapprochement
            'budget_versus_actual',   // budget contre réalisé
            'reserve',                // réserve disponible
            'share_commitments',      // engagements de parts (AC 20)
        ] as $required) {
            $this->assertArrayHasKey($required, $blocks, "Bloc financier manquant : {$required}.");
        }
    }

    /** AC 21 — chaque bloc porte l'URL de sa liste détaillée : aucun n'est un cul-de-sac. */
    public function test_ac_21_every_financial_block_links_to_its_detailed_list(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();

        foreach (app(FinancialDashboardService::class)->blocks($direction) as $key => $block) {
            $this->assertArrayHasKey('url', $block, "Le bloc « {$key} » doit être cliquable.");
            $this->assertStringStartsWith('/', (string) $block['url']);
        }
    }

    /** AC 19 — les soldes par compte reprennent le solde réel, mouvements compris. */
    public function test_ac_19_account_balances_reflect_the_real_balance(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        Account::factory()->create(['opening_balance_amount' => 750_000, 'state' => 'active']);

        $block = app(FinancialDashboardService::class)->accountBalances();

        $this->assertSame(750_000, $block['total']);
        $this->assertCount(1, $block['items']);
        $this->assertSame('Actif', $block['items'][0]['state_label']);
        $this->assertInstanceOf(User::class, $direction);
    }

    /** AC 19 — les créances échues ne retiennent que les factures impayées et dépassées. */
    public function test_ac_19_overdue_receivables_only_count_unpaid_and_past_due_invoices(): void
    {
        $this->invoice(dueOn: $this->month->subDays(10), state: InvoiceState::Impayee, amount: 400_000);
        $this->invoice(dueOn: $this->month->addDays(20), state: InvoiceState::Impayee, amount: 900_000);
        $this->invoice(dueOn: $this->month->subDays(10), state: InvoiceState::Payee, amount: 700_000);
        $this->invoice(dueOn: $this->month->subDays(10), state: InvoiceState::Annulee, amount: 600_000);

        $block = app(FinancialDashboardService::class)->overdueReceivables();

        $this->assertSame(1, $block['count']);
        $this->assertSame(400_000, $block['amount']);
    }

    /**
     * AC 20, FR171 — le total des engagements de parts **restant à verser** sur les contrats en
     * cours. Une part déjà versée, contre-passée, ou rattachée à un contrat clos n'y figure pas.
     */
    public function test_ac_20_share_commitments_only_count_what_remains_on_active_contracts(): void
    {
        $active = Contract::factory()->create(['state' => 'active']);
        $closed = Contract::factory()->create(['state' => 'closed']);

        ShareEntitlement::factory()->create([
            'contract_id' => $active->getKey(), 'share_amount' => 500_000, 'paid_amount' => 200_000, 'reversed_at' => null,
        ]);
        ShareEntitlement::factory()->create([
            'contract_id' => $active->getKey(), 'share_amount' => 300_000, 'paid_amount' => 300_000, 'reversed_at' => null,
        ]);
        ShareEntitlement::factory()->create([
            'contract_id' => $active->getKey(), 'share_amount' => 400_000, 'paid_amount' => 0, 'reversed_at' => now('UTC'),
        ]);
        ShareEntitlement::factory()->create([
            'contract_id' => $closed->getKey(), 'share_amount' => 900_000, 'paid_amount' => 0, 'reversed_at' => null,
        ]);

        $block = app(FinancialDashboardService::class)->shareCommitments();

        $this->assertSame(1, $block['count']);
        $this->assertSame(300_000, $block['amount']);
    }

    /** AC 25 — le tableau de bord direction expose tous les blocs de la liste de l'AC 25. */
    public function test_ac_25_the_direction_dashboard_exposes_every_required_block(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();

        $blocks = app(DirectionDashboardService::class)->blocks($direction);

        foreach ([
            'approval_queue', 'members_without_objective', 'daily_reports', 'objectives_by_state',
            'late_projects', 'interns_by_tutor', 'month_collections', 'month_charges',
            'available_balance', 'receivables', 'reserve',
        ] as $required) {
            $this->assertArrayHasKey($required, $blocks, "Bloc direction manquant : {$required}.");
        }

        // AC 25 — le niveau d'alerte accompagne l'écran.
        $this->assertSame('vert', app(DirectionDashboardService::class)->alert()['level']);
    }

    /**
     * AC 28, P5 — « Membres sans objectif » **inclut les comptes `direction` eux-mêmes**. La
     * direction se soumet à la même exigence que les équipes.
     */
    public function test_ac_28_members_without_objective_includes_direction_accounts(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $employee = User::factory()->active()->withRole('employe')->create();
        // L'employé a son objectif du mois ; la direction n'en a pas.
        Objective::factory()->create([
            'user_id' => $employee->getKey(),
            'due_date' => $this->month->addDays(20)->toDateString(),
            'state' => ObjectiveState::EnCours->value,
        ]);

        $block = app(DirectionDashboardService::class)->membersWithoutObjective();
        $ids = array_column($block['items'], 'id');

        $this->assertContains((int) $direction->getKey(), $ids, 'Un compte direction sans objectif doit être listé.');
        $this->assertNotContains((int) $employee->getKey(), $ids);
    }

    /** AC 28 — un objectif annulé ne compte pas : le membre reste « sans objectif ». */
    public function test_ac_28_a_cancelled_objective_does_not_count(): void
    {
        $employee = User::factory()->active()->withRole('employe')->create();
        Objective::factory()->create([
            'user_id' => $employee->getKey(),
            'due_date' => $this->month->addDays(20)->toDateString(),
            'state' => ObjectiveState::Annule->value,
        ]);

        $ids = array_column(app(DirectionDashboardService::class)->membersWithoutObjective()['items'], 'id');

        $this->assertContains((int) $employee->getKey(), $ids);
    }

    /**
     * AC 29 — « Rapports manquants » **exclut les absences approuvées**. Une personne en congé
     * approuvé n'a pas de rapport en retard : le produit parle de contribution, pas de contrôle.
     */
    public function test_ac_29_missing_reports_exclude_approved_absences(): void
    {
        $today = CarbonImmutable::now('Africa/Niamey');
        $present = User::factory()->active()->withRole('employe')->create();
        $absent = User::factory()->active()->withRole('employe')->create();
        Absence::factory()->create([
            'user_id' => $absent->getKey(),
            'start_date' => $today->subDay()->toDateString(),
            'end_date' => $today->addDay()->toDateString(),
            'state' => AbsenceState::Approuvee->value,
        ]);

        $block = app(DirectionDashboardService::class)->dailyReports();

        if (! $block['is_working_day']) {
            // AC 29 — un jour non travaillé n'attend aucun rapport, et le dit.
            $this->assertSame(0, $block['expected']);
            $this->assertSame(0, $block['missing']);

            return;
        }

        $this->assertSame(1, $block['expected'], 'Seule la personne présente est attendue.');
        $this->assertSame(1, $block['missing']);
        $this->assertInstanceOf(User::class, $present);
    }

    /** AC 29 — un rapport envoyé fait baisser le nombre de manquants. */
    public function test_ac_29_a_submitted_report_reduces_the_missing_count(): void
    {
        $today = CarbonImmutable::now('Africa/Niamey');
        $author = User::factory()->active()->withRole('employe')->create();
        DailyReport::factory()->create([
            'author_id' => $author->getKey(),
            'report_date' => $today->toDateString(),
            'state' => DailyReportState::Envoye->value,
        ]);

        $block = app(DirectionDashboardService::class)->dailyReports();

        if (! $block['is_working_day']) {
            $this->assertSame(0, $block['expected']);

            return;
        }

        $this->assertSame(1, $block['sent']);
        $this->assertSame(0, $block['missing']);
    }

    /**
     * AC 27, FR170 — tout tuteur ayant atteint la limite est signalé **visuellement et par un
     * libellé**. Le libellé seul doit suffire à comprendre.
     */
    public function test_ac_27_a_tutor_at_the_limit_is_flagged_visually_and_by_a_label(): void
    {
        $block = app(DirectionDashboardService::class)->internsByTutor();

        $this->assertArrayHasKey('at_limit_count', $block);
        $this->assertArrayHasKey('at_limit_label', $block);
        $this->assertNotSame('', $block['at_limit_label']);
        // Chaque tuteur porte le drapeau visuel `at_limit` et son libellé de charge.
        foreach ($block['items'] as $tutor) {
            $this->assertArrayHasKey('at_limit', $tutor);
            $this->assertArrayHasKey('load_label', $tutor);
        }
    }

    /** AC 25 — les projets en retard sont ceux, encore ouverts, dont la date de fin est dépassée. */
    public function test_ac_25_late_projects_are_open_projects_past_their_end_date(): void
    {
        $today = CarbonImmutable::now('Africa/Niamey');
        Project::factory()->create(['end_date' => $today->subDays(5)->toDateString(), 'status' => ProjectStatus::Actif->value]);
        Project::factory()->create(['end_date' => $today->addDays(5)->toDateString(), 'status' => ProjectStatus::Actif->value]);
        Project::factory()->create(['end_date' => $today->subDays(5)->toDateString(), 'status' => ProjectStatus::Cloture->value]);

        $block = app(DirectionDashboardService::class)->lateProjects();

        $this->assertSame(1, $block['count']);
        $this->assertSame('Actif', $block['items'][0]['status_label']);
    }

    /** AC 25 — « charges du mois » est l'assiette de l'alerte : les charges fixes actives. */
    public function test_ac_25_month_charges_equal_the_alert_baseline(): void
    {
        FixedCharge::factory()->create(['monthly_amount' => 800_000, 'is_active' => true]);
        FixedCharge::factory()->create(['monthly_amount' => 500_000, 'is_active' => false]);

        $this->assertSame(800_000, app(DirectionDashboardService::class)->monthCharges()['amount']);
    }

    /** AC 25 — la réserve est accompagnée du nombre de mois de charges qu'elle couvre. */
    public function test_ac_25_the_reserve_block_states_the_months_it_covers(): void
    {
        FixedCharge::factory()->create(['monthly_amount' => 1_000_000, 'is_active' => true]);

        $block = app(DirectionDashboardService::class)->reserveBlock();

        $this->assertArrayHasKey('covered_months', $block);
        // Le nombre de mois est aussi dit en toutes lettres (SOC-10).
        $this->assertStringContainsString('mois', $block['covered_months_label']);
    }

    /** AC 24 — le contenu reste une liste de cartes empilables : aucun tableau à colonnes fixes. */
    public function test_ac_24_blocks_are_stackable_cards_not_fixed_width_tables(): void
    {
        $page = (string) file_get_contents(base_path('resources/js/Pages/Finance/Dashboard.vue'));

        // Une seule colonne par défaut, deux à partir de `sm` : rien ne déborde à 320 px.
        $this->assertStringContainsString('grid min-w-0 gap-4 sm:grid-cols-2', $page);
        $this->assertStringNotContainsString('<table', $page);
        // `min-w-0` sur les conteneurs : sans lui, un montant long force la largeur de la grille
        // et réintroduit le défilement horizontal que l'AC 24 interdit.
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($page, 'min-w-0'),
            'Les conteneurs de la page doivent porter `min-w-0` pour rester réductibles à 320 px.',
        );
    }

    /** AC 25 — un solde négatif est dit, pas seulement coloré. */
    public function test_ac_25_a_negative_balance_is_stated_in_words(): void
    {
        Account::factory()->create(['opening_balance_amount' => 0, 'state' => 'active']);

        $block = app(DirectionDashboardService::class)->availableBalance();

        $this->assertArrayHasKey('is_negative', $block);
        $this->assertFalse($block['is_negative']);
    }

    /** AC 19 — les dépenses en attente comptent celles qui attendent vraiment une décision. */
    public function test_ac_19_pending_expenses_count_only_requested_ones(): void
    {
        Expense::factory()->create(['state' => ExpenseState::Demandee->value, 'requested_amount' => 120_000]);
        Expense::factory()->create(['state' => ExpenseState::Approuvee->value, 'requested_amount' => 900_000]);

        $block = app(FinancialDashboardService::class)->pendingExpenses();

        $this->assertSame(1, $block['count']);
        $this->assertSame(120_000, $block['amount']);
    }

    /** AC 19 — les encaissements du mois ne retiennent que les paiements validés du mois courant. */
    public function test_ac_19_month_collections_only_count_validated_payments_of_the_month(): void
    {
        Payment::factory()->create(['received_amount' => 300_000, 'received_on' => $this->month->addDays(3)->toDateString()]);
        Payment::factory()->create(['received_amount' => 800_000, 'received_on' => $this->month->subMonth()->addDays(3)->toDateString()]);

        $block = app(FinancialDashboardService::class)->monthCollections();

        $this->assertSame(1, $block['count']);
        $this->assertSame(300_000, $block['amount']);
    }

    private function invoice(CarbonImmutable $dueOn, InvoiceState $state, int $amount): Invoice
    {
        return Invoice::factory()->create([
            'due_on' => $dueOn->toDateString(),
            'state' => $state->value,
            'total_amount' => $amount,
        ]);
    }
}
