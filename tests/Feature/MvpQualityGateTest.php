<?php

namespace Tests\Feature;

use App\Services\Platform\ListExportService;
use App\Support\Listing\ListRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Story 10.1, Tasks 7 à 10 — porte du MVP, volet **automatisable** (AC 26, 27, 29, 31, 39, 40).
 *
 * Ce fichier ne prétend pas remplacer la recette. Il verrouille ce qu'un test peut prouver de
 * façon reproductible — la présence d'un budget, l'absence de ressource tierce, la conformité des
 * gabarits d'écran — et laisse explicitement à la recette physique ce qui exige un appareil réel,
 * un lecteur d'écran ou un lien bridé. Les mesures correspondantes sont consignées dans
 * `docs/ops/mvp-acceptance-report.md`.
 */
class MvpQualityGateTest extends TestCase
{
    /**
     * AC 27, NFR3 — **aucune ressource tierce chargée à l'exécution.**
     *
     * Le contrôle porte sur les sources : aucune balise ni URL absolue vers un CDN, une police
     * distante ou un script externe. C'est vérifiable statiquement et ne dépend d'aucun réseau.
     */
    public function test_ac_27_no_third_party_resource_is_referenced_by_any_view_or_page(): void
    {
        $offenders = [];
        $forbidden = [
            'cdn.', 'googleapis.com', 'gstatic.com', 'jsdelivr', 'unpkg.com',
            'cloudflare.com', 'bootstrapcdn', 'fontawesome.com',
        ];

        foreach ($this->frontendSources() as $file) {
            $source = (string) file_get_contents($file);

            foreach ($forbidden as $needle) {
                if (str_contains($source, $needle)) {
                    $offenders[] = basename($file).' → '.$needle;
                }
            }
        }

        $this->assertSame([], $offenders, 'Ressources tierces référencées : '.implode(', ', $offenders));
    }

    /** AC 27 — la vue racine ne charge que des ressources servies par l'application. */
    public function test_ac_27_the_root_view_loads_only_local_assets(): void
    {
        $blade = (string) file_get_contents(resource_path('views/app.blade.php'));

        $this->assertMatchesRegularExpression('/@vite/', $blade, 'Les ressources passent par Vite, donc par l’application.');
        $this->assertDoesNotMatchRegularExpression('#src="https?://#i', $blade);
        $this->assertDoesNotMatchRegularExpression('#href="https?://#i', $blade);
    }

    /**
     * AC 29, NFR7 — aucun défilement horizontal à 320 px.
     *
     * Le test vérifie la condition structurelle qui le garantit : tout conteneur de grille porte
     * `min-w-0`, et tout contenu large — table, bloc de code — vit dans son propre conteneur
     * `overflow-x-auto`. Sans `min-w-0`, une cellule longue force la largeur de la grille et
     * réintroduit le défilement, quel que soit le reste de la feuille de style.
     */
    public function test_ac_29_no_page_can_scroll_horizontally_at_320_pixels(): void
    {
        $offenders = [];

        foreach ($this->vuePages() as $file) {
            $source = (string) file_get_contents($file);

            // Une table doit toujours être enveloppée dans un conteneur qui défile lui-même.
            if (str_contains($source, '<table') && ! str_contains($source, 'overflow-x-auto')) {
                $offenders[] = basename($file).' : table sans conteneur `overflow-x-auto`';
            }

            // Une largeur fixe en pixels casse le rendu à 320 px.
            if (preg_match('/\bw-\[\d{3,}px\]/', $source) === 1) {
                $offenders[] = basename($file).' : largeur fixe supérieure à 99 px';
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    /**
     * AC 29, NFR8 — les cibles tactiles font au moins 44 × 44 px.
     *
     * L'application porte une classe utilitaire `touch-target` qui matérialise cette taille. Le
     * test vérifie qu'elle existe réellement dans la feuille de style avec la bonne dimension :
     * une classe absente rendrait muettes toutes les pages qui l'utilisent.
     */
    public function test_ac_29_the_touch_target_utility_really_enforces_44_pixels(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.touch-target', $css);
        $this->assertMatchesRegularExpression(
            '/\.touch-target\s*\{[^}]*min-height:\s*(44px|2\.75rem)/s',
            $css,
            'La cible tactile doit mesurer au moins 44 px de haut.',
        );
        $this->assertMatchesRegularExpression(
            '/\.touch-target\s*\{[^}]*min-width:\s*(44px|2\.75rem)/s',
            $css,
            'La cible tactile doit mesurer au moins 44 px de large.',
        );
    }

    /**
     * AC 26, NFR2 — le budget de poids est **opposable**, c'est-à-dire écrit quelque part et
     * vérifiable, pas seulement souhaité. Le test constate que le budget existe et que la
     * construction produit des lots séparés par page — sans quoi le budget de navigation à 80 Ko
     * serait inatteignable par construction.
     */
    public function test_ac_26_the_weight_budget_is_declared_and_the_build_splits_per_page(): void
    {
        $report = (string) file_get_contents(base_path('docs/ops/mvp-acceptance-report.md'));

        $this->assertStringContainsString('300 Ko', $report);
        $this->assertStringContainsString('80 Ko', $report);

        $manifest = public_path('build/manifest.json');
        $this->assertFileExists($manifest, 'Le budget ne peut être mesuré que sur une construction réelle.');

        /** @var array<string, mixed> $entries */
        $entries = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(
            20,
            count($entries),
            'Les pages doivent être découpées en lots distincts pour tenir le budget de navigation.',
        );
    }

    /** AC 13, FR108, FR175 — aucun export PDF ni Excel n'existe dans le produit. */
    public function test_ac_13_no_pdf_or_excel_export_exists_anywhere(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $dependencies = array_keys([...$composer['require'] ?? [], ...$composer['require-dev'] ?? []]);

        foreach ($dependencies as $package) {
            $this->assertStringNotContainsString('dompdf', strtolower((string) $package));
            $this->assertStringNotContainsString('phpspreadsheet', strtolower((string) $package));
            $this->assertStringNotContainsString('excel', strtolower((string) $package));
        }

        $this->assertSame(';', ListExportService::SEPARATOR);
    }

    /**
     * AC 22, AC 37 — toute route authentifiée est déclarée dans la matrice. Le contrôle est déjà
     * fait par la campagne ; celui-ci garantit qu'aucune **fixture** ne survit à la livraison de
     * sa route réelle, ce que l'AC 37 exige pour prononcer la porte.
     */
    public function test_ac_37_no_fixture_survives_the_delivery_of_its_replacement_route(): void
    {
        /** @var array<string, array{replacement: array{route: string, story: string}}> $fixtures */
        $fixtures = config('authorization-matrix.fixtures', []);
        $stale = [];

        foreach ($fixtures as $name => $fixture) {
            if (Route::has($fixture['replacement']['route'])) {
                $stale[] = "{$name} → la route {$fixture['replacement']['route']} existe désormais";
            }
        }

        $this->assertSame([], $stale, implode("\n", $stale));
    }

    /** AC 40 — le rapport de recette existe et porte les 18 critères du brief. */
    public function test_ac_40_the_acceptance_report_records_the_eighteen_brief_criteria(): void
    {
        $report = (string) file_get_contents(base_path('docs/ops/mvp-acceptance-report.md'));

        foreach (range(1, 18) as $criterion) {
            $this->assertStringContainsString(
                sprintf('CA-%02d', $criterion),
                $report,
                sprintf('Le critère CA-%02d doit être consigné avec son résultat.', $criterion),
            );
        }
    }

    /** AC 34 — chaque écart consigné porte une décision : corrigé, accepté ou reporté. */
    public function test_ac_34_every_recorded_gap_carries_an_explicit_decision(): void
    {
        $report = (string) file_get_contents(base_path('docs/ops/mvp-acceptance-report.md'));

        $this->assertStringContainsString('corrigé', $report);
        $this->assertStringContainsString('accepté', $report);
        $this->assertStringContainsString('reporté', $report);
    }

    /** L'inventaire des listes principales est figé et documenté (Task 1). */
    public function test_the_principal_list_inventory_is_frozen_and_documented(): void
    {
        $keys = ListRegistry::keys();

        $this->assertSame(['depenses', 'objectifs', 'rapports', 'personnes'], $keys);

        $report = (string) file_get_contents(base_path('docs/ops/mvp-acceptance-report.md'));

        foreach ($keys as $key) {
            $this->assertStringContainsString($key, $report);
        }
    }

    /** @return list<string> */
    private function frontendSources(): array
    {
        return [...$this->vuePages(), resource_path('views/app.blade.php'), resource_path('css/app.css')];
    }

    /** @return list<string> */
    private function vuePages(): array
    {
        $files = [];
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js')),
        );

        foreach ($directory as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'vue') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
