<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Rafraîchit la base de test en respectant la séparation des comptes MySQL.
 *
 * `RefreshDatabase` exécute `migrate:fresh` sur la connexion par défaut. Or cette
 * connexion porte le compte applicatif, qui n'a ni `DROP` ni `CREATE` : c'est
 * exactement ce que garantit la matrice de privilèges de
 * `docs/ops/database-users.md`, et le refus `DROP command denied` qui en découle
 * n'est pas un défaut de configuration mais la preuve que la matrice tient.
 *
 * Le DDL passe donc par la connexion de migration, seule détentrice de ces
 * droits, tandis que les tests continuent de s'exécuter sous le compte restreint
 * — la même asymétrie qu'en production.
 *
 * Sur SQLite en mémoire, l'exécution locale par défaut d'après `phpunit.xml`, il
 * n'y a pas deux comptes à séparer : le comportement d'origine est conservé.
 */
trait RefreshesSeparatedDatabase
{
    use RefreshDatabase {
        migrateDatabases as migrateDatabasesOnDefaultConnection;
    }
    use UsesSeparatedDatabaseConnections;

    protected function migrateDatabases(): void
    {
        if ($this->usingInMemoryDatabases()) {
            $this->migrateDatabasesOnDefaultConnection();

            return;
        }

        $this->artisan('migrate:fresh', array_merge($this->migrateFreshUsing(), [
            '--database' => $this->migrationConnectionName(),
        ]));
    }
}
