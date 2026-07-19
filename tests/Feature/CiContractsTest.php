<?php

namespace Tests\Feature;

use Tests\TestCase;

class CiContractsTest extends TestCase
{
    public function test_ac_1_pull_requests_run_all_required_quality_controls(): void
    {
        $workflow = $this->readFile('.github/workflows/pull-request-quality.yml');

        foreach ([
            'pull_request:',
            '- main',
            'vendor/bin/pint --test',
            'vendor/bin/phpstan analyse --memory-limit=1G --no-progress',
            'php artisan test',
            'npm run build',
        ] as $contract) {
            $this->assertStringContainsString($contract, $workflow);
        }

        foreach ($this->requiredChecks() as $check) {
            $this->assertStringContainsString("name: {$check}", $workflow);
        }
    }

    public function test_ac_2_workflow_declares_mysql_8_and_an_engine_guard(): void
    {
        $workflow = $this->readFile('.github/workflows/pull-request-quality.yml');

        $this->assertStringContainsString('image: mysql:8.0', $workflow);
        $this->assertStringContainsString('DB_CONNECTION: mysql', $workflow);
        $this->assertStringContainsString('DB_MIGRATION_USERNAME: staffptr_migrate_ci', $workflow);
        $this->assertStringContainsString('DB_USERNAME: staffptr_app_ci', $workflow);
        $this->assertStringContainsString('AUDIT_DB_APP_USERNAME: staffptr_app_ci', $workflow);
        $this->assertStringContainsString('mysqladmin ping', $workflow);
        $this->assertStringContainsString('php artisan db:show --database=mysql', $workflow);
        $this->assertStringContainsString('php artisan migrate:fresh --database=mysql_migration --force', $workflow);
        $this->assertFileExists(base_path('tests/Feature/CiDatabaseEngineTest.php'));
    }

    public function test_ac_3_bundle_budget_is_brotli_based_and_opposable(): void
    {
        $package = json_decode($this->readFile('package.json'), true);
        $script = $this->readFile('tests/ci/check-bundle-budget.mjs');

        $this->assertStringContainsString('--limit-kb=300', $package['scripts']['check:bundle'] ?? '');
        $this->assertStringContainsString('brotliCompressSync', $script);
        $this->assertStringContainsString('totalBytes <= limitBytes', $script);
        $this->assertStringContainsString('GITHUB_STEP_SUMMARY', $script);
    }

    public function test_ac_4_branch_protection_contract_uses_stable_required_checks(): void
    {
        $documentation = $this->readFile('docs/ops/continuous-integration.md');

        $this->assertStringContainsString('Mounkaila144/staffptr', $documentation);
        $this->assertStringContainsString('Pull request obligatoire', $documentation);

        foreach ($this->requiredChecks() as $check) {
            $this->assertStringContainsString("`{$check}`", $documentation);
        }
    }

    public function test_ac_5_playwright_has_a_real_demo_path_and_failure_diagnostics(): void
    {
        $workflow = $this->readFile('.github/workflows/pull-request-quality.yml');
        $test = $this->readFile('tests/e2e/demo.spec.js');

        $this->assertFileExists(base_path('playwright.config.js'));
        $this->assertStringContainsString("await page.goto('/')", $test);
        $this->assertStringContainsString('Fondation applicative opérationnelle', $test);
        $this->assertStringContainsString('trace:', $this->readFile('playwright.config.js'));
        $this->assertStringContainsString('if: failure()', $workflow);
    }

    public function test_ac_6_duration_is_measured_against_a_strict_ten_minute_limit(): void
    {
        $workflow = $this->readFile('.github/workflows/pull-request-quality.yml');
        $durationScript = $this->readFile('tests/ci/check-workflow-duration.mjs');

        $this->assertStringContainsString('name: CI Duration', $workflow);
        $this->assertStringContainsString('CI_DURATION_LIMIT_SECONDS: 600', $workflow);
        $this->assertStringContainsString('durationSeconds < limitSeconds', $durationScript);
        $this->assertStringContainsString('GITHUB_STEP_SUMMARY', $durationScript);
    }

    /** @return list<string> */
    private function requiredChecks(): array
    {
        return [
            'Pint',
            'Larastan',
            'PHPUnit (MySQL 8)',
            'Frontend Build & Budget',
            'Playwright',
            'CI Duration',
        ];
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertNotFalse($contents);

        return $contents;
    }
}
