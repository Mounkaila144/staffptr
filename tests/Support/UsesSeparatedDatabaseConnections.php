<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait UsesSeparatedDatabaseConnections
{
    protected function migrationConnectionName(): string
    {
        $configured = config('audit.database.migration_connection');

        return is_string($configured) && $configured !== ''
            ? $configured
            : (string) config('database.default');
    }

    protected function migrationSchema(): Builder
    {
        return Schema::connection($this->migrationConnectionName());
    }

    /** @param list<string> $privileges */
    protected function grantApplicationTablePrivileges(string $table, array $privileges): void
    {
        $connection = DB::connection($this->migrationConnectionName());

        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $username = config('audit.database.app_username');
        $host = config('audit.database.app_host');

        $this->assertIsString($username);
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_]+\z/', $username);
        $this->assertIsString($host);
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_.:%-]+\z/', $host);

        foreach ($privileges as $privilege) {
            $this->assertContains($privilege, ['SELECT', 'INSERT', 'UPDATE', 'DELETE']);
        }

        $database = '`'.str_replace('`', '``', $connection->getDatabaseName()).'`';
        $quotedTable = '`'.str_replace('`', '``', $table).'`';
        $privilegeList = implode(', ', $privileges);

        $connection->unprepared(
            "GRANT {$privilegeList} ON {$database}.{$quotedTable} TO '{$username}'@'{$host}'"
        );
    }

    /**
     * Remet le schéma à neuf après une preuve qui committe ses fixtures.
     *
     * Les preuves de concurrence doivent committer pour que deux processus se voient
     * réellement : elles ne peuvent donc pas compter sur le rollback habituel. Or
     * leurs traces d'audit sont immuables — rien ne les efface, et il n'est pas
     * question d'affaiblir cette garantie pour faire plaisir aux tests. La seule
     * remise à zéro légitime est donc structurelle, et elle relève du compte de
     * migration : l'application, elle, reste incapable de toucher à l'historique.
     *
     * Les privilèges accordés table par table survivent à un `DROP TABLE` sous MySQL,
     * la matrice n'a donc pas à être rejouée ensuite.
     */
    protected function restoreSchemaAfterCommittedProof(): void
    {
        $connectionName = $this->migrationConnectionName();

        if (DB::connection($connectionName)->getDriverName() === 'sqlite') {
            return;
        }

        $exitCode = Artisan::call('migrate:fresh', [
            '--database' => $connectionName,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
    }

    protected function requireMysqlProof(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return;
        }

        if (config('app.ci')) {
            $this->fail("La preuve d'immuabilité CI doit s'exécuter sous MySQL.");
        }

        $this->markTestSkipped('Preuve réservée à MySQL 8 dans GitHub Actions.');
    }
}
