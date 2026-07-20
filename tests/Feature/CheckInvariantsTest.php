<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Services\Platform\Invariants\EnvironmentInvariant;
use App\Services\Platform\Invariants\MySqlGrantInspector;
use App\Services\Platform\Invariants\SuperAdminPermissionInvariant;
use Illuminate\Console\Command;
use Tests\Support\IdentityTestCase;

class CheckInvariantsTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
        config(['app.env' => 'testing', 'app.debug' => true]);
    }

    public function test_ac_11_environment_invariant_accepts_expected_pairs(): void
    {
        $invariant = app(EnvironmentInvariant::class);

        foreach ([
            ['local', true],
            ['testing', true],
            ['staging', false],
            ['production', false],
        ] as [$environment, $debug]) {
            config(['app.env' => $environment, 'app.debug' => $debug]);
            $this->assertTrue($invariant->check()->passed, "{$environment} devait être cohérent.");
        }
    }

    public function test_ac_11_and_12_debug_discrepancy_is_named_and_returns_non_zero(): void
    {
        config(['app.env' => 'production', 'app.debug' => true]);

        $this->artisan('ptr:check-invariants')
            ->expectsOutputToContain('Cohérence APP_ENV / APP_DEBUG')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_ac_11_and_12_effective_business_permission_on_super_admin_is_detected(): void
    {
        $user = User::factory()->active()->create();
        $user->assignRole('super_admin');
        // Simule une donnée historique corrompue en contournant volontairement le service gardien.
        $user->givePermissionTo('depense.approuver');
        $result = app(SuperAdminPermissionInvariant::class)->check();

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('depense.approuver', $result->observed);

        $this->artisan('ptr:check-invariants')
            ->expectsOutputToContain('Permissions métier du super administrateur')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_ac_12_all_four_invariants_are_checked_before_the_command_fails(): void
    {
        config(['app.env' => 'production', 'app.debug' => true]);

        $this->artisan('ptr:check-invariants')
            ->expectsOutputToContain('Cohérence APP_ENV / APP_DEBUG')
            ->expectsOutputToContain('Permissions métier du super administrateur')
            ->expectsOutputToContain("Déclencheurs d'immuabilité du journal d'audit")
            ->expectsOutputToContain("Privilège DELETE du journal d'audit")
            ->assertExitCode(Command::FAILURE);
    }

    public function test_ac_11_mysql_and_mariadb_grants_cover_table_schema_and_global_scopes(): void
    {
        $inspection = app(MySqlGrantInspector::class)->inspect([
            'GRANT DELETE ON `staffptr`.`audit_logs` TO `app`@`localhost`',
            'GRANT SELECT, DELETE ON `staffptr`.* TO `app`@`localhost`',
            'GRANT ALL PRIVILEGES ON *.* TO `app`@`localhost` WITH GRANT OPTION',
            'GRANT DELETE ON `staffptr`.`model_has_roles` TO `app`@`localhost`',
            "GRANT USAGE ON *.* TO `app`@`localhost` IDENTIFIED BY PASSWORD '*HASH'",
        ], 'staffptr');

        $this->assertFalse($inspection['unparsed']);
        $this->assertCount(3, $inspection['violations']);
        $this->assertStringContainsString('audit_logs', $inspection['violations'][0]);
        $this->assertStringContainsString('staffptr`.*', $inspection['violations'][1]);
        $this->assertStringContainsString('ALL PRIVILEGES ON *.*', $inspection['violations'][2]);
        $this->assertStringNotContainsString('HASH', implode(' ', $inspection['violations']));
    }

    public function test_ac_11_unknown_grant_format_is_an_explicit_discrepancy(): void
    {
        $inspection = app(MySqlGrantInspector::class)->inspect([
            'FORMAT SERVEUR INCONNU',
        ], 'staffptr');

        $this->assertTrue($inspection['unparsed']);
        $this->assertSame([], $inspection['violations']);
    }
}
