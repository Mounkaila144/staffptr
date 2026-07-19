<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Builder;
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
