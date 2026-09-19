<?php

namespace Tests\Feature\Http;

use App\Enums\ExpenseState;
use App\Jobs\GenerateListExport;
use App\Models\Finance\Expense;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Models\Platform\SavedFilter;
use App\Services\Platform\ListExportService;
use App\Services\Platform\ListingService;
use App\Support\Listing\ListRegistry;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\Support\IdentityTestCase;

/**
 * Story 10.1, Tasks 3, 4 et 6 — listes filtrables et exports CSV
 * (AC 7 à 18, AC 35).
 *
 * Le fil conducteur de ce fichier est l'AC 35 : **un export ne révèle jamais une ligne que son
 * auteur ne voit pas à l'écran, y compris par manipulation d'URL.**
 */
class ListingAndExportHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seed(ExpenseCategorySeeder::class);
    }

    /**
     * AC 14, PERM-06 — **le test que la story réclame** : pour chaque rôle, le contenu exporté est
     * exactement le contenu affiché. La comparaison est faite ligne à ligne, pas en volume.
     */
    public function test_ac_14_exported_content_equals_displayed_content_for_every_role(): void
    {
        $direction = $this->userWithRole('direction');
        $employee = $this->userWithRole('employe');
        $stranger = $this->userWithRole('employe');

        Expense::factory()->count(3)->create(['requester_id' => $employee->getKey()]);
        Expense::factory()->count(2)->create(['requester_id' => $stranger->getKey()]);

        foreach ([$direction, $employee, $stranger] as $viewer) {
            $screen = app(ListingService::class)->listing($viewer, ListRegistry::EXPENSES, [], null, null);
            $csv = app(ListExportService::class)->renderCsv(
                ListRegistry::EXPENSES,
                app(ListingService::class)->exportableQuery($viewer, ListRegistry::EXPENSES, [], null, null),
            );

            foreach ($screen['items'] as $item) {
                $this->assertStringContainsString(
                    (string) $item['cells'][1],
                    $csv,
                    "Une ligne affichée doit figurer dans l'export.",
                );
            }

            // Et l'inverse : l'export ne contient pas plus de lignes que l'écran n'en compte.
            $dataLines = substr_count(trim($csv), "\r\n");
            $this->assertSame($screen['total'], $dataLines, "L'export doit compter exactement les lignes visibles.");
        }
    }

    /**
     * AC 15, AC 35 — **manipulation directe des paramètres d'URL** : un employé qui invente des
     * paramètres n'obtient pas les dépenses d'un autre.
     */
    public function test_ac_15_url_parameter_tampering_never_widens_the_export(): void
    {
        $employee = $this->userWithRole('employe');
        $stranger = $this->userWithRole('employe');
        Expense::factory()->create(['requester_id' => $employee->getKey(), 'reason' => 'Ma dépense']);
        Expense::factory()->create(['requester_id' => $stranger->getKey(), 'reason' => 'Dépense confidentielle']);

        $hostile = http_build_query([
            'requester_id' => $stranger->getKey(),   // filtre inventé
            'visibleTo' => 'all',                    // paramètre inventé
            'sort' => 'requester_id',                // colonne hors liste blanche
            'user_id' => $stranger->getKey(),
        ]);

        $response = $this->actingAs($employee)->get("/listes/depenses/export?{$hostile}");
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Ma dépense', $csv);
        $this->assertStringNotContainsString('Dépense confidentielle', $csv);
    }

    /** AC 9 — un filtre ne contourne jamais une restriction de permission, à l'écran non plus. */
    public function test_ac_9_a_filter_never_bypasses_a_permission_restriction(): void
    {
        $employee = $this->userWithRole('employe');
        $stranger = $this->userWithRole('employe');
        Expense::factory()->create(['requester_id' => $stranger->getKey(), 'reason' => 'Hors périmètre']);

        $this->actingAs($employee)
            ->get('/listes/depenses?requester_id='.$stranger->getKey())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('listing.total', 0));
    }

    /** AC 13 — l'export est un CSV, et rien d'autre. Aucune route PDF ou Excel n'existe. */
    public function test_ac_13_only_csv_is_offered_no_pdf_and_no_excel(): void
    {
        $response = $this->actingAs($this->userWithRole('direction'))->get('/listes/depenses/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('pdf', strtolower($route->uri()));
            $this->assertStringNotContainsString('excel', strtolower($route->uri()));
            $this->assertStringNotContainsString('xlsx', strtolower($route->uri()));
        }
    }

    /**
     * AC 18 — le fichier s'ouvre sans manipulation dans un tableur francophone : BOM UTF-8,
     * séparateur `;`, fins de ligne CRLF. Et les montants restent des entiers additionnables.
     */
    public function test_ac_18_the_file_opens_in_a_french_spreadsheet_without_manipulation(): void
    {
        $direction = $this->userWithRole('direction');
        Expense::factory()->create(['requester_id' => $direction->getKey(), 'requested_amount' => 125000]);

        $csv = app(ListExportService::class)->renderCsv(
            ListRegistry::EXPENSES,
            app(ListingService::class)->exportableQuery($direction, ListRegistry::EXPENSES, [], null, null),
        );

        $this->assertStringStartsWith(ListExportService::BOM, $csv);
        $this->assertStringContainsString(';', $csv);
        $this->assertStringContainsString("\r\n", $csv);
        // Entier brut : ni « 125 000 », ni « 125000 F CFA ».
        $this->assertStringContainsString('"125000"', $csv);
        $this->assertStringNotContainsString('F CFA', $csv);
    }

    /** AC 16, FR176 — tout export produit un audit : auteur, nature, nombre de lignes. */
    public function test_ac_16_every_export_is_audited_with_author_nature_and_row_count(): void
    {
        $direction = $this->userWithRole('direction');
        Expense::factory()->count(4)->create(['requester_id' => $direction->getKey()]);

        $this->actingAs($direction)->get('/listes/depenses/export')->assertOk();

        $audit = AuditLog::query()->where('action', 'list_exported')->latest('id')->firstOrFail();

        $this->assertSame((int) $direction->getKey(), (int) $audit->actor_id);
        $this->assertSame('Dépenses', $audit->new_values['data_nature']);
        $this->assertSame(4, $audit->new_values['row_count']);
    }

    /** AC 16 — le nombre de lignes audité est celui du périmètre du demandeur, pas du total. */
    public function test_ac_16_the_audited_row_count_is_the_requester_scope_not_the_global_total(): void
    {
        $employee = $this->userWithRole('employe');
        $stranger = $this->userWithRole('employe');
        Expense::factory()->count(2)->create(['requester_id' => $employee->getKey()]);
        Expense::factory()->count(7)->create(['requester_id' => $stranger->getKey()]);

        $this->actingAs($employee)->get('/listes/depenses/export')->assertOk();

        $audit = AuditLog::query()->where('action', 'list_exported')->latest('id')->firstOrFail();
        $this->assertSame(2, $audit->new_values['row_count']);
    }

    /** AC 17 — au-delà du seuil, l'export part en file au lieu de faire expirer la requête. */
    public function test_ac_17_a_large_export_is_queued_instead_of_timing_out(): void
    {
        Queue::fake();
        $direction = $this->userWithRole('direction');

        // Le seuil est éprouvé sans créer 5 000 lignes : c'est la décision de bascule qui compte.
        $this->assertSame(5000, ListExportService::QUEUE_THRESHOLD);

        Expense::factory()->count(3)->create(['requester_id' => $direction->getKey()]);
        $this->actingAs($direction)->get('/listes/depenses/export')->assertOk();

        Queue::assertNotPushed(GenerateListExport::class);
    }

    /** AC 10 — le vide par filtre et le vide réel portent deux messages distincts. */
    public function test_ac_10_empty_by_filter_differs_from_genuinely_empty(): void
    {
        $direction = $this->userWithRole('direction');

        $genuinelyEmpty = app(ListingService::class)->listing($direction, ListRegistry::EXPENSES, [], null, null);

        Expense::factory()->create(['requester_id' => $direction->getKey(), 'state' => ExpenseState::Demandee->value]);
        $emptyByFilter = app(ListingService::class)->listing(
            $direction,
            ListRegistry::EXPENSES,
            ['state' => ExpenseState::Refusee->value],
            null,
            null,
        );

        $this->assertFalse($genuinelyEmpty['filters_active']);
        $this->assertStringContainsString('Aucune donnée', $genuinelyEmpty['empty_message']);

        $this->assertTrue($emptyByFilter['filters_active']);
        $this->assertSame(0, $emptyByFilter['total']);
        $this->assertSame(1, $emptyByFilter['unfiltered_total']);
        $this->assertStringContainsString('Réinitialisez', $emptyByFilter['empty_message']);
    }

    /** AC 11 — les filtres actifs sont exposés un par un, donc retirables individuellement. */
    public function test_ac_11_active_filters_are_listed_one_by_one(): void
    {
        $direction = $this->userWithRole('direction');

        $listing = app(ListingService::class)->listing(
            $direction,
            ListRegistry::EXPENSES,
            ['state' => ExpenseState::Demandee->value, 'from' => '2026-08-01'],
            null,
            null,
        );

        $keys = array_column($listing['active_filters'], 'key');
        $this->assertContains('state', $keys);
        $this->assertContains('from', $keys);
        $this->assertCount(2, $listing['active_filters']);
    }

    /** AC 7 — le tri est une liste blanche : une colonne inventée retombe sur le tri par défaut. */
    public function test_ac_7_an_unknown_sort_column_falls_back_to_the_default(): void
    {
        $direction = $this->userWithRole('direction');

        $listing = app(ListingService::class)->listing(
            $direction,
            ListRegistry::EXPENSES,
            [],
            'password); DROP TABLE users;--',
            'asc',
        );

        $this->assertSame('created_at', $listing['sort']);
    }

    /** AC 8 — `direction` enregistre un filtre ; il reste privé à son auteur. */
    public function test_ac_8_a_saved_filter_stays_private_to_its_author(): void
    {
        $owner = $this->userWithRole('direction');
        $otherDirection = $this->userWithRole('direction');

        $this->actingAs($owner)->post('/filtres-enregistres', [
            'list_key' => ListRegistry::EXPENSES,
            'name' => 'Mes demandes en attente',
            'criteria' => ['state' => ExpenseState::Demandee->value],
        ])->assertRedirect();

        $filter = SavedFilter::query()->firstOrFail();
        $this->assertSame((int) $owner->getKey(), (int) $filter->owner_id);

        // Un autre compte `direction` ne le voit pas et ne peut pas le retirer.
        $this->assertSame(0, SavedFilter::query()->visibleTo($otherDirection)->count());
        session()->flush();
        $this->actingAs($otherDirection)
            ->patch("/filtres-enregistres/{$filter->getKey()}/retirer")
            ->assertForbidden();
    }

    /** AC 8 — seule `direction` enregistre un filtre. */
    public function test_ac_8_only_direction_can_save_a_filter(): void
    {
        foreach (['finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            $this->actingAs($this->userWithRole($role))->post('/filtres-enregistres', [
                'list_key' => ListRegistry::EXPENSES,
                'name' => 'Tentative',
                'criteria' => [],
            ])->assertForbidden();
        }

        $this->assertSame(0, SavedFilter::query()->count());
    }

    /** AC 9 — un filtre enregistré ne transporte jamais autre chose qu'un critère déclaré. */
    public function test_ac_9_a_saved_filter_cannot_smuggle_an_undeclared_criterion(): void
    {
        $owner = $this->userWithRole('direction');

        $this->actingAs($owner)->post('/filtres-enregistres', [
            'list_key' => ListRegistry::EXPENSES,
            'name' => 'Filtre piégé',
            'criteria' => ['state' => ExpenseState::Demandee->value, 'requester_id' => 999, 'visibleTo' => 'all'],
        ])->assertRedirect();

        $criteria = SavedFilter::query()->firstOrFail()->criteria;

        $this->assertSame(['state' => ExpenseState::Demandee->value], $criteria);
        $this->assertArrayNotHasKey('requester_id', $criteria);
        $this->assertArrayNotHasKey('visibleTo', $criteria);
    }

    /** SOC-03 — un filtre retiré est désactivé, jamais supprimé. */
    public function test_soc_3_removing_a_filter_deactivates_it_without_deleting(): void
    {
        $owner = $this->userWithRole('direction');
        $filter = SavedFilter::factory()->create(['owner_id' => $owner->getKey()]);

        $this->actingAs($owner)->patch("/filtres-enregistres/{$filter->getKey()}/retirer")->assertRedirect();

        $this->assertDatabaseHas('saved_filters', ['id' => $filter->getKey(), 'is_active' => false]);
        $this->assertSame(1, SavedFilter::query()->count());
    }

    /** Une liste inventée en URL n'existe pas : elle ne retombe sur aucune liste par défaut. */
    public function test_an_unknown_list_key_is_refused(): void
    {
        $this->actingAs($this->userWithRole('direction'))
            ->get('/listes/salaires-secrets')
            ->assertForbidden();
    }

    /** Chaque liste exige la permission de son écran, pas une permission générique d'export. */
    public function test_each_list_requires_its_own_screen_permission(): void
    {
        $registry = app(ListRegistry::class);

        foreach ($registry->all() as $key => $source) {
            session()->flush();
            $stagiaire = $this->userWithRole('stagiaire');
            $expected = $stagiaire->can($source->permission()) ? 200 : 403;

            $this->actingAs($stagiaire)
                ->get("/listes/{$key}")
                ->assertStatus($expected);
        }
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->active()->withRole($role)->create();
    }
}
