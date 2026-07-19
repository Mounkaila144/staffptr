<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvironmentContractTest extends TestCase
{
    public function test_ac_1_four_environments_and_public_domains_are_documented(): void
    {
        $documentation = $this->readFile('docs/ops/environments.md');

        foreach (['Local', 'CI', 'Préproduction', 'Production'] as $environment) {
            $this->assertStringContainsString("| {$environment} |", $documentation);
        }

        $this->assertStringContainsString('https://staging.staff.ptrniger.com', $documentation);
        $this->assertStringContainsString('https://staff.ptrniger.com', $documentation);
    }

    public function test_ac_2_staging_is_dedicated_safe_and_redis_backed(): void
    {
        $documentation = $this->readFile('docs/ops/environments.md');

        foreach ([
            'MySQL dédiée `ptrstaff_staging`',
            'hôte virtuel',
            'APP_DEBUG=false',
            'QUEUE_CONNECTION=redis',
            'MAIL_MAILER=log',
            'aucun envoi',
            '`ptr:anonymize`',
            'valider les migrations **et les restaurations**',
        ] as $contract) {
            $this->assertStringContainsString($contract, $documentation);
        }
    }

    public function test_ac_2_dec_05_isolation_and_redis_values_are_explicit(): void
    {
        $documentation = $this->readFile('docs/ops/environments.md');
        $environment = $this->readFile('.env.example');

        foreach ([
            'utilisateur système PTR Staff dédié',
            'pool PHP-FPM 8.3 dédié',
            '`REDIS_PREFIX`, `REDIS_DB` et `REDIS_CACHE_DB`',
            "surveillance de l'espace disque",
            'Condition de révision',
            'REDIS_PREFIX=ptrstaff_staging_',
            'REDIS_DB=10',
            'REDIS_PREFIX=ptrstaff_prod_',
            'REDIS_DB=12',
            'cache:clear',
        ] as $contract) {
            $this->assertStringContainsString($contract, $documentation);
        }

        $this->assertSame('ptrstaff_local_', $this->activeEnvironmentValue($environment, 'REDIS_PREFIX'));
        $this->assertSame('0', $this->activeEnvironmentValue($environment, 'REDIS_DB'));
        $this->assertSame('1', $this->activeEnvironmentValue($environment, 'REDIS_CACHE_DB'));
    }

    public function test_ac_4_privileged_credentials_have_a_process_scoped_injection_contract(): void
    {
        $documentation = $this->readFile('docs/ops/environments.md');
        $environment = $this->readFile('.env.example');

        foreach ([
            'DEPLOY_HOST',
            'DEPLOY_PORT',
            'DEPLOY_USER',
            'DEPLOY_SSH_PRIVATE_KEY',
            'DB_MIGRATION_HOST',
            'DB_MIGRATION_PORT',
            'DB_MIGRATION_DATABASE',
            'DB_MIGRATION_USERNAME',
            'DB_MIGRATION_PASSWORD',
            'uniquement au processus',
            'écrites sur disque',
            'php artisan config:cache',
            'sans accès `root`',
        ] as $contract) {
            $this->assertStringContainsString($contract, $documentation);
        }

        $this->assertDoesNotMatchRegularExpression('/^DB_MIGRATION_(?:USERNAME|PASSWORD)=/m', $environment);
        $this->assertMatchesRegularExpression('/^# DB_MIGRATION_USERNAME=/m', $environment);
        $this->assertMatchesRegularExpression('/^# DB_MIGRATION_PASSWORD=/m', $environment);
    }

    public function test_ac_5_shared_environment_and_app_key_protection_are_operationally_defined(): void
    {
        $documentation = $this->readFile('docs/ops/environments.md');

        foreach ([
            'shared/.env',
            'chmod 600',
            'propriétaire, le groupe et les permissions',
            'php artisan key:generate',
            'sauvegardée hors ligne',
            'même si une sauvegarde de base valide existe',
            'séparément et manuellement',
        ] as $contract) {
            $this->assertStringContainsString($contract, $documentation);
        }
    }

    public function test_ac_6_backup_secret_locations_are_named_but_unset_until_dec_06(): void
    {
        $documentation = $this->readFile('docs/ops/environments.md');

        foreach ($this->backupSecrets() as $secret) {
            $this->assertStringContainsString("`{$secret}`", $documentation);
            $this->assertDoesNotMatchRegularExpression('/'.preg_quote($secret, '/').'\s*=\s*\S+/', $documentation);
        }

        $this->assertStringContainsString('DEC-06', $documentation);
        $this->assertStringContainsString('Seule la story 11.1', $documentation);
        $this->assertStringContainsString('hors du serveur', $documentation);
    }

    public function test_ac_7_every_declared_secret_has_a_rotation_entry_and_rollback(): void
    {
        $rotation = $this->readFile('docs/ops/secrets-rotation.md');

        foreach ([
            'ptrstaff_prod_app',
            'ptrstaff_staging_app',
            'ptrstaff_prod_migrate',
            'ptrstaff_staging_migrate',
            'APP_KEY',
            'DEPLOY_SSH_PRIVATE_KEY',
            ...$this->backupSecrets(),
        ] as $secret) {
            $this->assertStringContainsString("`{$secret}`", $rotation);
        }

        $this->assertGreaterThanOrEqual(5, substr_count($rotation, 'retour arrière'));
        $this->assertStringContainsString('point de bascule', $rotation);
        $this->assertStringContainsString('php artisan env:encrypt', $rotation);
        $this->assertStringContainsString('RETAIN CURRENT PASSWORD', $rotation);
    }

    /** @return list<string> */
    private function backupSecrets(): array
    {
        return [
            'BACKUP_OBJECT_ENDPOINT',
            'BACKUP_OBJECT_BUCKET',
            'BACKUP_OBJECT_ACCESS_KEY_ID',
            'BACKUP_OBJECT_SECRET_ACCESS_KEY',
            'BACKUP_ARCHIVE_PASSPHRASE',
        ];
    }

    private function activeEnvironmentValue(string $environment, string $key): ?string
    {
        preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $environment, $matches);

        return $matches[1] ?? null;
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertNotFalse($contents);

        return $contents;
    }
}
