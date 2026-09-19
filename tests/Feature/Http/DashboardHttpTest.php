<?php

namespace Tests\Feature\Http;

use App\Models\Finance\FixedCharge;
use App\Models\Identity\User;
use App\Services\Finance\FinancialDashboardService;
use App\Services\Platform\DirectionDashboardService;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

/**
 * Story 9.1, Tasks 5, 6 et 8 — cloisonnement et coût des deux tableaux de bord
 * (AC 22, AC 23, AC 26, AC 31, AC 42, AC 43).
 */
class DashboardHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    /**
     * AC 22 — le tableau de bord financier est accessible à `finance` et `direction`, et à eux
     * seuls. L'accès par URL directe depuis tout autre rôle est refusé.
     */
    public function test_ac_22_the_financial_dashboard_is_reserved_to_finance_and_direction(): void
    {
        foreach (['direction', 'finance'] as $role) {
            // La session est vidée entre deux rôles : sans cela, la session de la requête
            // précédente reste liée au compte précédent et la suivante repart à la connexion.
            session()->flush();
            $this->actingAs($this->userWithRole($role))
                ->get('/finances/tableau-de-bord')
                ->assertOk();
        }

        foreach (['super_admin', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            $this->actingAs($this->userWithRole($role))
                ->get('/finances/tableau-de-bord')
                // 403 franc : ni 302, ni contenu partiel (SOC-01).
                ->assertForbidden();
        }
    }

    /** SOC-01 — un visiteur non authentifié est renvoyé à la connexion, jamais servi. */
    public function test_an_unauthenticated_visitor_never_reaches_a_dashboard(): void
    {
        $this->get('/finances/tableau-de-bord')->assertRedirect(route('login'));
        $this->get('/tableau-de-bord/direction')->assertRedirect(route('login'));
    }

    /**
     * AC 23, AC 43, FR172 — **aucun bloc non autorisé n'est rendu, même vide**. Un `tuteur` a bien
     * accès au tableau de bord direction, mais aucune de ses clés financières ne doit exister dans
     * la charge utile : leur absence est la garantie, pas leur valeur nulle.
     */
    public function test_ac_23_no_unauthorised_block_is_rendered_even_empty(): void
    {
        $tutor = $this->userWithRole('tuteur');

        $blocks = app(DirectionDashboardService::class)->blocks($tutor);

        foreach (['approval_queue', 'month_collections', 'available_balance', 'receivables', 'reserve'] as $forbidden) {
            $this->assertArrayNotHasKey(
                $forbidden,
                $blocks,
                "Le bloc « {$forbidden} » ne doit pas exister pour un tuteur, même vide.",
            );
        }

        // Ce à quoi le tuteur a bien droit reste présent : le cloisonnement n'ampute pas son écran.
        $this->assertArrayHasKey('interns_by_tutor', $blocks);
        $this->assertArrayHasKey('daily_reports', $blocks);
    }

    /** AC 23 — même règle sur le tableau de bord financier, bloc par bloc. */
    public function test_ac_23_the_financial_dashboard_omits_unauthorised_blocks(): void
    {
        $finance = $this->userWithRole('finance');
        $direction = $this->userWithRole('direction');
        $service = app(FinancialDashboardService::class);

        // `finance` n'approuve pas les dépenses mais consulte tout le reste.
        $this->assertArrayHasKey('account_balances', $service->blocks($finance));
        $this->assertArrayHasKey('share_commitments', $service->blocks($direction));

        // Aucun bloc ne survit à la perte de sa permission.
        foreach ($service->definitions() as $key => $definition) {
            $this->assertTrue(
                $finance->can($definition['permission']) === array_key_exists($key, $service->blocks($finance)),
                "Le bloc « {$key} » doit apparaître si et seulement si sa permission est accordée.",
            );
        }
    }

    /**
     * AC 26, FR167 — « En attente de mon approbation » reste en **première position**. L'ordre des
     * clés retournées est l'ordre de rendu : le vérifier ici suffit à verrouiller la position.
     */
    public function test_ac_26_the_approval_queue_block_comes_first(): void
    {
        $direction = $this->userWithRole('direction');

        $keys = array_keys(app(DirectionDashboardService::class)->blocks($direction));

        $this->assertSame('approval_queue', $keys[0]);
    }

    /**
     * AC 31 — le nombre de requêtes d'un rendu complet est plafonné. C'est l'écran le plus dense
     * du produit et son principal risque N+1 : le plafond échoue dès qu'un bloc introduit une
     * boucle de requêtes.
     */
    public function test_ac_31_a_full_direction_dashboard_render_stays_under_the_query_ceiling(): void
    {
        $direction = $this->userWithRole('direction');
        FixedCharge::factory()->count(5)->create();
        // Des données en volume : le nombre de requêtes ne doit pas en dépendre.
        User::factory()->count(15)->active()->withRole('employe')->create();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        app(DirectionDashboardService::class)->blocks($direction);

        $this->assertLessThanOrEqual(
            DirectionDashboardService::MAX_QUERIES,
            $queries,
            sprintf(
                'Le rendu a coûté %d requêtes pour un plafond de %d. Un bloc a probablement introduit une boucle.',
                $queries,
                DirectionDashboardService::MAX_QUERIES,
            ),
        );
    }

    /** AC 4 — le niveau d'alerte est envoyé avec la page, libellé compris. */
    public function test_ac_4_the_alert_level_is_rendered_with_its_textual_label(): void
    {
        $this->actingAs($this->userWithRole('direction'))
            ->get('/tableau-de-bord/direction')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/DirectionDashboard')
                ->where('alert.level', 'vert')
                ->where('alert.level_label', 'Vert')
                ->has('alert.method')
                ->has('alert.source_date'));
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->active()->withRole($role)->create();
    }
}
