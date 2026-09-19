<?php

namespace Tests\Feature;

use SplFileInfo;
use Tests\TestCase;

/**
 * Story 9.1, Task 11 — traçabilité des 44 critères d'acceptation et porte du Jalon 4.
 *
 * Chaque AC doit être nommé par au moins un test de la story. La vérification est mécanique : elle
 * échoue dès qu'un critère perd sa couverture, y compris par simple renommage d'un test.
 */
class EpicNineTraceabilityTest extends TestCase
{
    /**
     * Critères qui ne peuvent pas être prouvés par la suite PHPUnit seule.
     *
     * - **AC 30 et AC 42** : premier rendu utile sous 3 secondes en 3G dégradée à 320 px. La
     *   mesure exige un navigateur réel sous bridage réseau ; elle est faite en recette et suivie
     *   dans le Dev Agent Record. Ce qui est automatisable — cloisonnement, plafond de requêtes,
     *   différé des agrégats — l'est et vit dans `DashboardHttpTest`.
     * - **AC 34** : le comptage des interactions se fait à l'écran. Le test automatisé prouve la
     *   condition nécessaire — chaque notification porte une URL directe autorisée.
     * - **AC 38** : la supervision de la file relève de la story 11.3, non livrée ici.
     *
     * @var list<int>
     */
    private const MANUAL_ACCEPTANCE_CRITERIA = [30, 38, 42];

    /** Fichiers de test livrés par la story 9.1. */
    private const EPIC_NINE_TEST_FILES = [
        'Unit/AlertLevelCalculatorTest.php',
        'Feature/AlertLevelServiceTest.php',
        'Feature/AlertLevelEffectsTest.php',
        'Feature/CorrectionPlanTest.php',
        'Feature/EpicNineNotificationTest.php',
        'Feature/EpicNineDashboardContentTest.php',
        'Feature/Http/DashboardHttpTest.php',
        'Feature/Http/CorrectionPlanHttpTest.php',
    ];

    public function test_every_acceptance_criterion_is_named_by_at_least_one_test(): void
    {
        $covered = $this->coveredAcceptanceCriteria();
        $missing = [];

        foreach (range(1, 44) as $criterion) {
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
    public function test_every_epic_nine_test_file_is_present(): void
    {
        foreach (self::EPIC_NINE_TEST_FILES as $file) {
            $this->assertFileExists(base_path("tests/{$file}"), "Fichier de test manquant : {$file}.");
        }
    }

    /**
     * **Porte du Jalon 4, AC 40** — la règle métier bloquante « les parts de 10 % et 30 % restent
     * payables en alerte rouge » conserve son test dédié et nommé. Son absence bloque la porte
     * qualité : c'est le garde-fou contre une garde d'alerte réintroduite par mégarde dans
     * `ShareEntitlementService`.
     */
    public function test_the_named_blocking_rule_test_for_shares_in_red_is_present(): void
    {
        $path = base_path('tests/Feature/AlertLevelEffectsTest.php');

        $this->assertFileExists($path);
        $source = (string) file_get_contents($path);

        $this->assertStringContainsString(
            'test_blocking_rule_13_shares_of_ten_and_thirty_percent_remain_payable_in_red',
            $source,
            'Le test bloquant no 13 doit conserver son nom : la porte qualité le cherche par ce nom.',
        );
        $this->assertStringContainsString('CONTRA-07', $source);
        $this->assertStringContainsString('RM-14', $source);
    }

    /**
     * AC 40 — les quatre effets du rouge et de l'orange sont prouvés, chacun par son test nommé.
     */
    public function test_the_four_bounded_effects_each_keep_a_named_test(): void
    {
        $source = (string) file_get_contents(base_path('tests/Feature/AlertLevelEffectsTest.php'));

        foreach ([
            // 1. Le rouge refuse l'activation d'un nouveau compte.
            'test_ac_8_activating_a_new_employee_account_is_refused_in_red',
            // 2. Le rouge avertit sans bloquer sur une dépense non essentielle.
            'test_ac_9_a_non_essential_expense_warns_but_stays_approvable_in_red',
            // 3. Le rouge ne retient aucun versement de part.
            'test_blocking_rule_13_shares_of_ten_and_thirty_percent_remain_payable_in_red',
            // 4. Aucune personne n'est jamais bloquée.
            'test_ac_12_a_red_alert_changes_no_state_role_permission_or_session',
        ] as $test) {
            $this->assertStringContainsString($test, $source, "Effet borné sans test nommé : {$test}.");
        }

        // Le quatrième effet, l'exigence de plan en orange, est prouvé dans son propre fichier.
        $planSource = (string) file_get_contents(base_path('tests/Feature/CorrectionPlanTest.php'));
        $this->assertStringContainsString('test_ac_11_direction_is_reminded_while_no_plan_exists', $planSource);
        $this->assertStringContainsString('test_ac_16_reminders_stop_as_soon_as_the_plan_exists', $planSource);
    }

    /**
     * AC 41 — la dépendance avant de 7.3 est fermée, et le point de contrôle provisoire de la
     * story 7.1 ne prétend plus que le niveau est toujours vert.
     */
    public function test_ac_41_the_forward_dependency_of_story_7_3_is_closed(): void
    {
        $seven = (string) file_get_contents(base_path('tests/Feature/InternActivationTest.php'));

        $this->assertStringNotContainsString(
            'answers_green_until_epic_nine',
            $seven,
            "Le point de contrôle provisoire de 7.1 doit être remplacé par le calcul réel de l'Epic 9.",
        );
        $this->assertStringContainsString(
            'test_ac_8_activating_a_new_intern_account_is_refused_in_red',
            (string) file_get_contents(base_path('tests/Feature/AlertLevelEffectsTest.php')),
        );
    }

    /**
     * AC 39 — l'assiette provient **exclusivement** du paramétrage. Aucun montant de charge fixe
     * n'est codé en dur dans le calcul ni dans le service.
     */
    public function test_ac_39_no_fixed_charge_amount_is_hard_coded_in_the_alert_calculation(): void
    {
        foreach ([
            'app/Services/Finance/AlertLevelCalculator.php',
            'app/Services/Finance/AlertLevelService.php',
        ] as $relative) {
            $source = (string) file_get_contents(base_path($relative));

            $this->assertStringNotContainsString(
                'FixedCharge::query()->whereIn',
                $source,
                "{$relative} ne doit filtrer aucune charge fixe par une liste nommée.",
            );
        }

        $service = (string) file_get_contents(base_path('app/Services/Finance/AlertLevelService.php'));
        $this->assertStringContainsString(
            "FixedCharge::query()->active()->sum('monthly_amount')",
            $service,
            "L'assiette doit rester la somme des charges fixes actives, sans exception.",
        );
    }

    /**
     * Critères nommés par un test, lus dans les noms de méthodes et les blocs de documentation.
     *
     * @return list<int>
     */
    private function coveredAcceptanceCriteria(): array
    {
        $covered = [];

        foreach (self::EPIC_NINE_TEST_FILES as $relative) {
            $file = new SplFileInfo(base_path("tests/{$relative}"));

            if (! $file->isFile()) {
                continue;
            }

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
            static fn (int $number): bool => $number >= 1 && $number <= 44,
        )));
    }
}
