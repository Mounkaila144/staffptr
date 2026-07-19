<?php

namespace Tests\Feature;

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

    public function test_ac_3_delete_is_never_granted_to_a_business_or_audit_table(): void
    {
        $allowed = ['cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'sessions'];

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
                    "/GRANT SELECT, INSERT, UPDATE, DELETE ON `{$schema}`\.`{$table}`/",
                    $sql,
                );
            }
        }
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

    private function sqlModel(): string
    {
        $documentation = $this->readFile('docs/ops/database-users.md');
        preg_match('/```sql\n(.*?)\n```/s', $documentation, $matches);

        $this->assertArrayHasKey(1, $matches);

        return $matches[1];
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertNotFalse($contents);

        return $contents;
    }
}
