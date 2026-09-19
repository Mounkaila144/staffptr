<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabasePrivilegeContractTest extends TestCase
{
    public function test_ac_3_provisioning_creates_four_accounts_idempotently_without_literal_passwords(): void
    {
        $sql = $this->sqlModel();

        foreach ($this->accounts() as $account => $schema) {
            $this->assertStringContainsString("CREATE USER IF NOT EXISTS '{$account}'@'localhost'", $sql);
            $this->assertStringContainsString("ALTER USER '{$account}'@'localhost'", $sql);
            $this->assertStringContainsString("`{$schema}`", $sql);
        }

        $this->assertSame(2, substr_count($sql, 'CREATE DATABASE IF NOT EXISTS'));
        preg_match_all("/IDENTIFIED BY '([^']+)'/", $sql, $passwords);
        $this->assertNotEmpty($passwords[1]);

        foreach ($passwords[1] as $password) {
            $this->assertMatchesRegularExpression('/^\{\{[A-Z0-9_]+\}\}$/', $password);
        }
    }

    public function test_ac_3_migration_accounts_receive_all_only_on_their_own_schema(): void
    {
        $sql = $this->sqlModel();

        foreach ([
            'ptrstaff_prod_migrate' => 'ptrstaff_prod',
            'ptrstaff_staging_migrate' => 'ptrstaff_staging',
        ] as $account => $schema) {
            $this->assertStringContainsString(
                "GRANT ALL PRIVILEGES ON `{$schema}`.*\n  TO '{$account}'@'localhost' WITH GRANT OPTION;",
                $sql,
            );
        }
    }

    public function test_task_0_delete_is_limited_to_infrastructure_and_two_audited_rbac_pivots(): void
    {
        $allowed = [
            'cache',
            'cache_locks',
            'failed_jobs',
            'job_batches',
            'jobs',
            'model_has_permissions',
            'model_has_roles',
            'sessions',
        ];

        preg_match_all('/GRANT [^;]*DELETE ON `[^`]+`\.`([^`]+)`/i', $this->sqlModel(), $matches);
        $tables = array_values(array_unique($matches[1]));
        sort($tables);

        $this->assertSame($allowed, $tables);
        $this->assertNotContains('audit_logs', $tables);
    }

    public function test_ac_3_delete_is_explicitly_granted_to_every_infrastructure_table(): void
    {
        $sql = $this->sqlModel();

        foreach (['ptrstaff_prod', 'ptrstaff_staging'] as $schema) {
            foreach (['sessions', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks'] as $table) {
                $this->assertMatchesRegularExpression(
                    "/GRANT UPDATE, DELETE ON `{$schema}`\.`{$table}`/",
                    $sql,
                );
            }
        }
    }

    public function test_ac_3_bis_schema_level_grants_to_application_accounts_carry_only_select_and_insert(): void
    {
        $grants = $this->schemaLevelApplicationGrants($this->sqlModel());

        $this->assertCount(2, $grants, 'Chaque compte applicatif doit porter un grant de schéma.');

        foreach ($grants as $account => $privileges) {
            $this->assertSame(
                'SELECT, INSERT',
                $privileges,
                "Le grant de schéma de {$account} ne peut porter que SELECT et INSERT : ".
                'un UPDATE ou un DELETE accordé au schéma ne se reprend plus table par table.',
            );
        }
    }

    public function test_ac_3_bis_audit_logs_never_receives_update_or_delete(): void
    {
        $reaching = array_filter(
            $this->applicationGrants($this->sqlModel()),
            static fn (array $grant): bool => $grant['table'] === '*' || $grant['table'] === 'audit_logs',
        );

        $this->assertNotEmpty($reaching, 'Aucun grant applicatif atteignant audit_logs n’a été analysé.');

        foreach ($reaching as $grant) {
            $path = $grant['table'] === '*'
                ? "le schéma `{$grant['schema']}`, qui couvre audit_logs sans la nommer"
                : 'la table `audit_logs` directement';

            foreach (['UPDATE', 'DELETE'] as $privilege) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $privilege,
                    $grant['privileges'],
                    "Le compte {$grant['account']} atteint audit_logs par {$path} et y obtiendrait ".
                    "{$privilege}. Le journal d'audit est en ajout seul : aucun niveau de grant ne ".
                    'peut porter UPDATE ni DELETE.',
                );
            }
        }
    }

    public function test_ac_3_every_table_named_by_phase_two_is_created_by_a_migration(): void
    {
        $tables = array_values(array_unique(array_map(
            static fn (array $grant): string => $grant['table'],
            array_filter(
                $this->applicationGrants($this->sqlModel()),
                static fn (array $grant): bool => $grant['table'] !== '*',
            ),
        )));

        $this->assertNotEmpty($tables, 'La phase 2 du modèle SQL doit nommer des tables.');

        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(0, $exitCode, Artisan::output());

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "La table `{$table}` est contractualisée par la phase 2 du modèle SQL mais n'est ".
                "créée par aucune migration : le GRANT échouerait à l'exécution (ERROR 1146), ".
                'comme constaté sur le VPS le 19/07/2026.',
            );
        }
    }

    public function test_ac_3_bis_ci_provisioning_mirrors_the_privilege_matrix(): void
    {
        $workflow = $this->readFile('.github/workflows/pull-request-quality.yml');

        $this->assertStringContainsString(
            "GRANT SELECT, INSERT ON staffptr_test.* TO 'staffptr_app_ci'@'%';",
            $workflow,
            'Sans parité entre la CI et la matrice, le « refusé par défaut » ne serait vérifié nulle part.',
        );

        foreach (['sessions', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks'] as $table) {
            $this->assertStringContainsString(
                "GRANT UPDATE, DELETE ON staffptr_test.{$table} TO 'staffptr_app_ci'@'%';",
                $workflow,
                'La phase 2 de la matrice doit être appliquée en CI après la migration : un GRANT sur '.
                "la table absente `{$table}` rendrait la chaîne rouge au lieu d'attendre la préproduction.",
            );
        }

        foreach (['people', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'login_attempts'] as $table) {
            $this->assertStringContainsString(
                "GRANT UPDATE ON staffptr_test.{$table} TO 'staffptr_app_ci'@'%';",
                $workflow,
            );
        }

        foreach (['people', 'users', 'roles', 'permissions', 'role_has_permissions', 'login_attempts'] as $table) {
            $this->assertStringNotContainsString(
                "GRANT UPDATE, DELETE ON staffptr_test.{$table}",
                $workflow,
            );
            $this->assertStringNotContainsString(
                "GRANT DELETE ON staffptr_test.{$table}",
                $workflow,
            );
        }

        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            $this->assertStringContainsString(
                "GRANT DELETE ON staffptr_test.{$table} TO 'staffptr_app_ci'@'%';",
                $workflow,
            );
        }

        preg_match_all(
            "/GRANT ([^;]+?) ON staffptr_test\.\* TO 'staffptr_app_ci'@'%'/i",
            $workflow,
            $matches,
        );

        foreach ($matches[1] as $privileges) {
            $this->assertStringNotContainsStringIgnoringCase('UPDATE', $privileges);
            $this->assertStringNotContainsStringIgnoringCase('DELETE', $privileges);
            $this->assertStringNotContainsStringIgnoringCase('ALL', $privileges);
        }
    }

    public function test_ac_3_bis_runbook_carries_the_standing_grant_update_instruction(): void
    {
        $documentation = $this->readFile('docs/ops/database-users.md');
        $header = substr($documentation, 0, (int) strpos($documentation, '## Comptes et frontières'));

        $this->assertStringContainsString('Consigne permanente', $header);
        $this->assertStringContainsString('story 2.1', $header);
        $this->assertStringContainsString('GRANT UPDATE', $header);
        $this->assertStringContainsString('cumulatifs', $documentation);

        foreach (['ptrstaff_prod', 'ptrstaff_staging'] as $schema) {
            foreach (['people', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'login_attempts'] as $table) {
                $this->assertStringContainsString(
                    "GRANT UPDATE ON `{$schema}`.`{$table}` TO",
                    $documentation,
                );
            }

            foreach (['model_has_roles', 'model_has_permissions'] as $table) {
                $this->assertStringContainsString(
                    "GRANT DELETE ON `{$schema}`.`{$table}` TO",
                    $documentation,
                );
            }
        }
    }

    /**
     * @return array<string, string> compte applicatif => liste de privilèges accordés au schéma
     */
    private function schemaLevelApplicationGrants(string $sql): array
    {
        preg_match_all(
            "/^GRANT ([^;]+?) ON `[^`]+`\.\* TO '([^']+_app)'@'localhost';/im",
            $sql,
            $matches,
            PREG_SET_ORDER,
        );

        $grants = [];

        foreach ($matches as $match) {
            $grants[$match[2]] = trim($match[1]);
        }

        return $grants;
    }

    public function test_ac_3_future_tables_default_to_delete_refused(): void
    {
        $documentation = $this->readFile('docs/ops/database-users.md');

        $this->assertStringContainsString('toute table créée ultérieurement', $documentation);
        $this->assertStringContainsString('**refusé par défaut**', $documentation);
        $this->assertStringContainsString('exception motivée', $documentation);
    }

    public function test_ac_3_every_grant_is_schema_scoped_and_account_isolated(): void
    {
        $sql = $this->sqlModel();

        $this->assertStringNotContainsString('ON *.*', $sql);
        $this->assertStringNotContainsString('SUPER', $sql);
        preg_match_all(
            "/GRANT [^;]+ ON `([^`]+)`\.(?:`[^`]+`|\*)\s+TO '([^']+)'@'localhost'/i",
            $sql,
            $grants,
            PREG_SET_ORDER,
        );
        $this->assertNotEmpty($grants);

        foreach ($grants as $grant) {
            $this->assertSame($this->accounts()[$grant[2]], $grant[1]);
        }
    }

    public function test_ac_3_audit_grant_remains_configuration_driven_without_real_account_names(): void
    {
        $migration = $this->readFile('database/migrations/2026_07_19_052353_create_audit_logs_table.php');

        $this->assertStringContainsString("config('audit.database.app_username')", $migration);
        $this->assertStringContainsString("config('audit.database.app_host')", $migration);

        foreach (array_keys($this->accounts()) as $account) {
            $this->assertStringNotContainsString($account, $migration);
        }
    }

    public function test_ac_1_identity_migrations_grant_update_without_delete(): void
    {
        foreach ([
            'database/migrations/2026_07_19_230525_create_people_table.php',
            'database/migrations/2026_07_19_230526_create_users_table.php',
        ] as $migrationPath) {
            $migration = $this->readFile($migrationPath);

            $this->assertStringContainsString('GRANT UPDATE ON', $migration);
            $this->assertStringNotContainsString('GRANT DELETE', $migration);
            $this->assertStringContainsString("config('audit.database.app_username')", $migration);
            $this->assertStringContainsString("config('audit.database.app_host')", $migration);
        }
    }

    public function test_ac_1_rbac_migration_grants_only_the_reviewed_privileges(): void
    {
        $migration = $this->readFile('database/migrations/2026_07_20_070505_create_permission_tables.php');

        foreach (['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'] as $table) {
            $this->assertStringContainsString("'{$table}'", $migration);
        }

        $this->assertStringContainsString('GRANT UPDATE ON', $migration);
        $this->assertStringContainsString('GRANT DELETE ON', $migration);
        $this->assertStringContainsString("'model_has_permissions'", $migration);
        $this->assertStringContainsString("'model_has_roles'", $migration);
        $this->assertStringContainsString("config('audit.database.app_username')", $migration);
        $this->assertStringContainsString("config('audit.database.app_host')", $migration);
    }

    public function test_story_2_6_login_attempt_migration_grants_update_without_delete(): void
    {
        $migration = $this->readFile(
            'database/migrations/2026_07_20_132351_create_login_attempts_table.php',
        );

        $this->assertStringContainsString('GRANT UPDATE ON', $migration);
        $this->assertStringNotContainsString('GRANT DELETE', $migration);
        $this->assertStringContainsString("config('audit.database.app_username')", $migration);
        $this->assertStringContainsString("config('audit.database.app_host')", $migration);
    }

    /** @return array<string, string> */
    private function accounts(): array
    {
        return [
            'ptrstaff_prod_app' => 'ptrstaff_prod',
            'ptrstaff_prod_migrate' => 'ptrstaff_prod',
            'ptrstaff_staging_app' => 'ptrstaff_staging',
            'ptrstaff_staging_migrate' => 'ptrstaff_staging',
        ];
    }

    /**
     * Concatène **tous** les blocs SQL du runbook. Ne lire que le premier laisserait un futur
     * bloc porteur de `GRANT` échapper à l'intégralité des vérifications de ce fichier.
     */
    private function sqlModel(): string
    {
        $documentation = $this->readFile('docs/ops/database-users.md');
        preg_match_all('/```sql\n(.*?)\n```/s', $documentation, $matches);

        $this->assertArrayHasKey(1, $matches);
        $this->assertNotEmpty($matches[1]);

        return implode("\n", $matches[1]);
    }

    /**
     * Analyse les `GRANT` réellement exécutables destinés aux comptes applicatifs. Les lignes
     * commentées sont écartées : elles ne s'exécutent pas.
     *
     * @return list<array{account: string, schema: string, table: string, privileges: string}>
     */
    private function applicationGrants(string $sql): array
    {
        $executable = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';

        preg_match_all(
            "/GRANT\s+([^;]+?)\s+ON\s+`([^`]+)`\.(?:`([^`]+)`|\*)\s+TO\s+'([^']+_app)'@'[^']+'/i",
            $executable,
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(static fn (array $match): array => [
            'account' => $match[4],
            'schema' => $match[2],
            'table' => ($match[3] ?? '') !== '' ? $match[3] : '*',
            'privileges' => trim($match[1]),
        ], $matches);
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertNotFalse($contents);

        return $contents;
    }
}
