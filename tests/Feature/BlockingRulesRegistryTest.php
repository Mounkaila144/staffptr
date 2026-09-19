<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Story 10.1, Task 6 — inventaire des **quatorze règles métier bloquantes**
 * (architecture § 23.2, AC 23, AC 36).
 *
 * « L'absence d'un seul de ces tests bloque la porte de qualité. » Cet inventaire rend cette
 * phrase exécutable : chaque règle nomme son fichier et sa méthode de test, et l'inventaire échoue
 * en listant précisément ce qui manque.
 *
 * Le contrôle est volontairement **statique** — il lit les sources plutôt que d'exécuter les tests.
 * Il répond à « le test existe-t-il et porte-t-il toujours ce nom ? », pas à « passe-t-il ? », qui
 * est le travail de la suite elle-même. Un test renommé ou supprimé est ainsi détecté même si la
 * suite reste verte par ailleurs.
 */
class BlockingRulesRegistryTest extends TestCase
{
    /**
     * Les quatorze règles, leur énoncé et le test nommé qui les prouve.
     *
     * @return array<int, array{rule: string, reference: string, file: string, method: string}>
     */
    public static function blockingRules(): array
    {
        return [
            1 => [
                'rule' => 'Maximum 3 objectifs majeurs validés par personne et par mois',
                'reference' => 'RM-05, CA-05',
                'file' => 'tests/Feature/WorkModuleLifecycleTest.php',
                'method' => 'ac_7_to_13_objective_proposal_and_monthly_limit_apply_to_direction_too',
            ],
            2 => [
                'rule' => "Maximum 5 priorités mensuelles d'entreprise",
                'reference' => 'RM-04',
                'file' => 'tests/Feature/WorkModuleLifecycleTest.php',
                'method' => 'ac_1_2_3_and_6_company_priorities_enforce_five_and_cancel_without_deletion',
            ],
            3 => [
                'rule' => 'Maximum 3 stagiaires actifs par tuteur',
                'reference' => 'RM-06, CA-04',
                'file' => 'tests/Feature/BlockingRule3TutorInternLimitTest.php',
                'method' => 'test_ac_21_the_third_intern_is_accepted_and_the_fourth_is_refused',
            ],
            4 => [
                'rule' => 'Deux approbateurs distincts, sans seuil de montant',
                'reference' => 'RM-09, CA-09',
                'file' => 'tests/Feature/ExpenseApprovalLifecycleTest.php',
                'method' => 'ac_1_expense_is_approved_only_after_two_distinct_direction_approvals',
            ],
            5 => [
                'rule' => "Le demandeur n'est jamais approbateur, même `direction`",
                'reference' => 'RM-10, CA-11',
                'file' => 'tests/Feature/ExpenseApprovalLifecycleTest.php',
                'method' => 'ac_3_direction_requester_never_approves_own_expense_with_exact_message',
            ],
            6 => [
                'rule' => 'Préparateur ≠ contrôleur sur rapprochement et rapport mensuel',
                'reference' => 'RM-16, FR151',
                'file' => 'tests/Feature/ReconciliationLifecycleTest.php',
                'method' => 'test_ac_78_to_84_difference_is_always_calculated_controller_is_distinct_and_correction_is_versioned',
            ],
            7 => [
                'rule' => 'Suppression financière impossible — modèle, route et base',
                'reference' => 'RM-17, CA-12',
                'file' => 'tests/Feature/BlockingRule7FinancialDeletionTest.php',
                'method' => 'test_blocking_rule_7_the_database_itself_refuses_a_direct_delete',
            ],
            8 => [
                'rule' => 'Aucune écriture imputable sur un mois clôturé',
                'reference' => 'FR158, FR114',
                'file' => 'tests/Feature/MonthGuardTest.php',
                'method' => 'test_ac_33_closed_month_is_named_and_rejected',
            ],
            9 => [
                'rule' => '`super_admin` n’a aucune permission métier',
                'reference' => 'PERM-03',
                'file' => 'tests/Feature/BlockingRule9SuperAdminTest.php',
                'method' => 'test_blocking_rule_9_super_admin_receives_403_for_all_four_business_powers',
            ],
            10 => [
                'rule' => 'La suspension invalide toutes les sessions immédiatement',
                'reference' => 'FR8, PERM-08',
                'file' => 'tests/Feature/AccountLifecycleSessionRevocationTest.php',
                'method' => 'test_blocking_rule_10_suspension_revokes_two_database_sessions_immediately',
            ],
            11 => [
                'rule' => "L'échec d'écriture d'audit annule l'opération métier",
                'reference' => 'NFR21',
                'file' => 'tests/Feature/AuditTransactionTest.php',
                'method' => 'test_rule_11_audit_write_failure_rolls_back_the_business_operation',
            ],
            12 => [
                'rule' => 'Unicité du téléphone sur comptes non archivés uniquement',
                'reference' => 'FR3',
                'file' => 'tests/Feature/PhoneUniquenessRuleTest.php',
                'method' => 'test_rule_12_phone_is_unique_only_among_non_archived_accounts',
            ],
            13 => [
                'rule' => 'Les parts 10 % / 30 % restent payables en alerte rouge',
                'reference' => 'RM-14, FR165',
                'file' => 'tests/Feature/AlertLevelEffectsTest.php',
                'method' => 'test_blocking_rule_13_shares_of_ten_and_thirty_percent_remain_payable_in_red',
            ],
            14 => [
                'rule' => 'La somme des parts est exactement égale à la base',
                'reference' => 'FR130, NFR22',
                'file' => 'tests/Unit/ShareCalculatorTest.php',
                'method' => 'test_ac_16_sum_is_exact_for_many_integer_bases',
            ],
        ];
    }

    /**
     * AC 23, AC 36 — chacune des quatorze règles dispose d'un test nommé, présent et retrouvable.
     */
    public function test_ac_23_every_one_of_the_fourteen_blocking_rules_has_a_named_test(): void
    {
        $missing = [];

        foreach (self::blockingRules() as $number => $entry) {
            $path = base_path($entry['file']);

            if (! is_file($path)) {
                $missing[] = "Règle {$number} ({$entry['reference']}) : fichier absent — {$entry['file']}";

                continue;
            }

            $source = (string) file_get_contents($path);

            if (! str_contains($source, $entry['method'])) {
                $missing[] = "Règle {$number} ({$entry['reference']}) : test nommé absent — {$entry['method']} dans {$entry['file']}";
            }
        }

        $this->assertSame(
            [],
            $missing,
            "La porte de qualité est bloquée. Règles sans test nommé :\n".implode("\n", $missing),
        );
    }

    /** L'inventaire couvre exactement quatorze règles — ni treize, ni quinze. */
    public function test_ac_23_the_registry_covers_exactly_fourteen_rules(): void
    {
        $rules = self::blockingRules();

        $this->assertCount(14, $rules);
        $this->assertSame(range(1, 14), array_keys($rules));
    }

    /**
     * AC 36 — l'inventaire lui-même reste synchronisé avec l'architecture : chaque règle y est
     * décrite avec sa référence, ce qui interdit d'y glisser une entrée vide pour faire nombre.
     */
    public function test_ac_36_every_registry_entry_names_its_rule_and_reference(): void
    {
        foreach (self::blockingRules() as $number => $entry) {
            $this->assertNotSame('', trim($entry['rule']), "Règle {$number} sans énoncé.");
            $this->assertNotSame('', trim($entry['reference']), "Règle {$number} sans référence.");
            $this->assertStringStartsWith('tests/', $entry['file']);
        }
    }
}
