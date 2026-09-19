<?php

namespace Tests\Feature;

use SplFileInfo;
use Tests\TestCase;

/**
 * Story 10.1, Task 10 — traçabilité des 40 critères d'acceptation et porte du MVP.
 *
 * Chaque AC doit être nommé par au moins un test de la story. La vérification est mécanique :
 * elle échoue dès qu'un critère perd sa couverture, y compris par simple renommage.
 */
class EpicTenTraceabilityTest extends TestCase
{
    /**
     * Critères qui ne peuvent pas être prouvés par la suite PHPUnit seule.
     *
     * Ils exigent un appareil réel, un lecteur d'écran, un lien bridé ou une dépendance non
     * livrée. Chacun est consigné dans `docs/ops/mvp-acceptance-report.md` avec son état, et
     * chacun **bloque la porte MVP** tant qu'il n'est pas relevé — ce n'est pas une dispense,
     * c'est le constat de ce qu'un test ne peut pas faire.
     *
     * - **25, 28, 30, 32, 33** : mesures physiques (réseau bridé, téléphone, lecteur d'écran,
     *   jeu de capacité sur le VPS cible).
     * - **24** : l'alerte d'exploitation sur échec d'invariant relève de la supervision 11.4.
     *
     * @var list<int>
     */
    private const MANUAL_ACCEPTANCE_CRITERIA = [24, 25, 28, 30, 32, 33];

    /** Fichiers de test livrés par la story 10.1. */
    private const EPIC_TEN_TEST_FILES = [
        'Feature/Http/SearchHttpTest.php',
        'Feature/Http/ListingAndExportHttpTest.php',
        'Feature/BlockingRulesRegistryTest.php',
        'Feature/BlockingRule7FinancialDeletionTest.php',
        'Feature/MvpQualityGateTest.php',
        'Feature/InvariantsScheduleTest.php',
    ];

    public function test_every_acceptance_criterion_is_named_by_at_least_one_test(): void
    {
        $covered = $this->coveredAcceptanceCriteria();
        $missing = [];

        foreach (range(1, 40) as $criterion) {
            if (in_array($criterion, self::MANUAL_ACCEPTANCE_CRITERIA, true)) {
                continue;
            }

            if (! in_array($criterion, $covered, true)) {
                $missing[] = $criterion;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Critères sans test nommé : '.implode(', ', $missing).'.',
        );
    }

    /** Aucun fichier de test de la story n'a été supprimé ni renommé sans mise à jour d'ici. */
    public function test_every_epic_ten_test_file_is_present(): void
    {
        foreach (self::EPIC_TEN_TEST_FILES as $file) {
            $this->assertFileExists(base_path("tests/{$file}"), "Fichier de test manquant : {$file}.");
        }
    }

    /**
     * AC 36 — l'inventaire des quatorze règles bloquantes existe et est exécutable. C'est lui qui
     * rend la phrase « l'absence d'un seul de ces tests bloque la porte » vérifiable.
     */
    public function test_ac_36_the_fourteen_blocking_rules_registry_is_executable(): void
    {
        $source = (string) file_get_contents(base_path('tests/Feature/BlockingRulesRegistryTest.php'));

        $this->assertStringContainsString('test_ac_23_every_one_of_the_fourteen_blocking_rules_has_a_named_test', $source);
        $this->assertStringContainsString('test_ac_23_the_registry_covers_exactly_fourteen_rules', $source);
    }

    /**
     * AC 35 — le test central de la story : un export ne révèle jamais une ligne cachée à l'écran,
     * y compris par manipulation d'URL. Ses deux méthodes gardent leur nom.
     */
    public function test_ac_35_the_export_scope_tests_keep_their_names(): void
    {
        $source = (string) file_get_contents(base_path('tests/Feature/Http/ListingAndExportHttpTest.php'));

        $this->assertStringContainsString('test_ac_14_exported_content_equals_displayed_content_for_every_role', $source);
        $this->assertStringContainsString('test_ac_15_url_parameter_tampering_never_widens_the_export', $source);
        $this->assertStringContainsString('test_ac_16_every_export_is_audited_with_author_nature_and_row_count', $source);
    }

    /**
     * AC 38, AC 39 — la porte MVP n'est pas prononcée à la légère : le rapport de recette doit
     * dire explicitement ce qui reste ouvert, et non conclure à la conformité.
     */
    public function test_ac_39_the_report_does_not_declare_the_gate_passed_while_measures_are_missing(): void
    {
        $report = (string) file_get_contents(base_path('docs/ops/mvp-acceptance-report.md'));

        $this->assertStringContainsString('la porte MVP n’est pas franchie', str_replace("'", '’', $report));
        $this->assertStringContainsString('À mesurer', $report);
    }

    /**
     * Critères nommés par un test, lus dans les noms de méthodes et les blocs de documentation.
     *
     * @return list<int>
     */
    private function coveredAcceptanceCriteria(): array
    {
        $covered = [];

        foreach (self::EPIC_TEN_TEST_FILES as $relative) {
            $file = new SplFileInfo(base_path("tests/{$relative}"));

            if (! $file->isFile()) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            preg_match_all('/\bac_(\d+)(?:_and_(\d+))*/', $source, $methodMatches, PREG_SET_ORDER);

            foreach ($methodMatches as $match) {
                foreach (array_slice($match, 1) as $number) {
                    if ($number !== '') {
                        $covered[] = (int) $number;
                    }
                }
            }

            preg_match_all('/AC\s+((?:\d+\s*(?:,|et|à)?\s*)+)/u', $source, $docMatches);

            foreach ($docMatches[1] ?? [] as $group) {
                preg_match_all('/\d+/', $group, $numbers);
                $found = array_map('intval', $numbers[0]);

                if (str_contains($group, 'à') && count($found) >= 2) {
                    $covered = array_merge($covered, range($found[0], $found[count($found) - 1]));

                    continue;
                }

                $covered = array_merge($covered, $found);
            }
        }

        return array_values(array_unique(array_filter(
            $covered,
            static fn (int $number): bool => $number >= 1 && $number <= 40,
        )));
    }
}
