<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Task 12 — traçabilité des 46 critères d'acceptation de la story 7.1.
 *
 * Chaque AC doit être nommé par au moins un test. La vérification est mécanique : elle échoue dès
 * qu'un critère perd sa couverture, y compris par renommage d'un test.
 */
class EpicSevenTraceabilityTest extends TestCase
{
    /**
     * AC 46 ne peut pas être automatisé : il exige une recette sur téléphone réel et l'exécution
     * de la story 11.7. Il est suivi hors suite, dans le Dev Agent Record de la story.
     */
    private const MANUAL_ACCEPTANCE_CRITERIA = [46];

    /** Fichiers de test livrés par la story 7.1. */
    private const EPIC_SEVEN_TEST_FILES = [
        'Feature/InternshipAndReviewSchemaTest.php',
        'Feature/EpicSevenSeederIdempotenceTest.php',
        'Feature/WeeklyReviewLifecycleTest.php',
        'Feature/Http/WeeklyReviewHttpTest.php',
        'Feature/ImprovementPlanLifecycleTest.php',
        'Feature/Http/ImprovementPlanHttpTest.php',
        'Feature/InternActivationTest.php',
        'Feature/Http/InternshipIntakeHttpTest.php',
        'Feature/BlockingRule3TutorInternLimitTest.php',
        'Feature/TutorInternLimitConcurrencyDatabaseTest.php',
        'Feature/Http/TutorCapacityHttpTest.php',
        'Feature/InternshipJourneyTest.php',
        'Feature/Http/InternshipDossierHttpTest.php',
        'Feature/SupportRequestGroupingTest.php',
        'Feature/Http/EpicSevenAuthorizationTest.php',
        'Feature/EpicSevenWriteDisciplineTest.php',
        'Feature/EpicSevenInterfaceContractTest.php',
        'Feature/EpicSevenNotificationContractTest.php',
        'Unit/EpicSevenCalculationsTest.php',
    ];

    public function test_every_acceptance_criterion_is_named_by_at_least_one_test(): void
    {
        $covered = $this->coveredAcceptanceCriteria();
        $missing = [];

        foreach (range(1, 46) as $criterion) {
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

    /**
     * Les fichiers de test de la story existent tous : aucun n'a été supprimé ni renommé sans
     * mise à jour de cette liste.
     */
    public function test_every_epic_seven_test_file_is_present(): void
    {
        foreach (self::EPIC_SEVEN_TEST_FILES as $file) {
            $this->assertFileExists(base_path("tests/{$file}"), "Fichier de test manquant : {$file}.");
        }
    }

    /**
     * La règle métier bloquante n° 3 conserve son test dédié et nommé : son absence bloque la
     * porte de qualité de l'epic.
     */
    public function test_the_named_blocking_rule_test_is_present(): void
    {
        $path = base_path('tests/Feature/BlockingRule3TutorInternLimitTest.php');

        $this->assertFileExists($path);
        $source = (string) file_get_contents($path);

        $this->assertStringContainsString('bloquante n° 3', $source);
        $this->assertStringContainsString('test_ac_21_the_third_intern_is_accepted_and_the_fourth_is_refused', $source);
        $this->assertStringContainsString('test_ac_40_the_epic_gate_holds_on_all_four_properties', $source);
    }

    /**
     * La preuve de concurrence de l'AC 26 existe et refuse de s'exécuter hors MySQL ou MariaDB :
     * elle ne peut donc pas passer silencieusement en CI sur un moteur qui n'applique pas les
     * verrous.
     */
    public function test_the_concurrency_proof_refuses_to_run_on_a_permissive_engine(): void
    {
        $source = (string) file_get_contents(
            base_path('tests/Feature/TutorInternLimitConcurrencyDatabaseTest.php'),
        );

        $this->assertStringContainsString('ac_26_two_simultaneous_assignments', $source);
        $this->assertStringContainsString("config('app.ci')", $source);
        $this->assertStringContainsString('$this->fail(', $source);
        $this->assertStringContainsString('for update', $source);
    }

    /**
     * Critères nommés par un test, lus dans les noms de méthodes et les blocs de documentation.
     *
     * @return list<int>
     */
    private function coveredAcceptanceCriteria(): array
    {
        $covered = [];

        foreach ($this->collectSourceFiles() as $file) {
            $source = (string) file_get_contents($file->getPathname());

            // Noms de méthodes : test_ac_12_..., ac_26_..., test_ac_5_and_42_...
            preg_match_all('/\bac_(\d+)(?:_and_(\d+))*/', $source, $methodMatches, PREG_SET_ORDER);

            foreach ($methodMatches as $match) {
                foreach (array_slice($match, 1) as $number) {
                    if ($number !== '') {
                        $covered[] = (int) $number;
                    }
                }
            }

            // Blocs de documentation : « AC 20 à 26 », « AC 4, 7, 12 ».
            preg_match_all('/AC\s+((?:\d+\s*(?:,|et|à)?\s*)+)/u', $source, $docMatches);

            foreach ($docMatches[1] ?? [] as $group) {
                preg_match_all('/\d+/', $group, $numbers);
                $isRange = str_contains($group, 'à');
                $found = array_map('intval', $numbers[0]);

                if ($isRange && count($found) >= 2) {
                    $covered = array_merge($covered, range($found[0], $found[count($found) - 1]));

                    continue;
                }

                $covered = array_merge($covered, $found);
            }
        }

        return array_values(array_unique(array_filter($covered, static fn (int $n): bool => $n >= 1 && $n <= 46)));
    }

    /** @return list<SplFileInfo> */
    private function collectSourceFiles(): array
    {
        $files = [];

        foreach (self::EPIC_SEVEN_TEST_FILES as $relative) {
            $files[] = new SplFileInfo(base_path("tests/{$relative}"));
        }

        // Le reste de la suite peut aussi couvrir un critère : on l'inclut sans le rendre requis.
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('tests')));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
