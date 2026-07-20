<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var bool */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection($this->connectionName());

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // information_schema.TRIGGERS exige le privilège TRIGGER, lequel autoriserait aussi le
        // compte applicatif à supprimer les barrières. Cette vue à droits du définisseur expose
        // uniquement les noms nécessaires au contrôle, sans élargir les droits d'écriture.
        $quotedDatabase = $connection->getPdo()->quote($connection->getDatabaseName());

        if (! is_string($quotedDatabase)) {
            throw new RuntimeException("Le schéma courant n'a pas pu être cité pour la vue d'invariants.");
        }

        $connection->unprepared(<<<SQL
            CREATE OR REPLACE SQL SECURITY DEFINER VIEW audit_trigger_metadata AS
            SELECT LOWER(TRIGGER_NAME) AS trigger_name
            FROM information_schema.TRIGGERS
            WHERE TRIGGER_SCHEMA = {$quotedDatabase}
              AND EVENT_OBJECT_TABLE = 'audit_logs'
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection($this->connectionName());

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $connection->unprepared('DROP VIEW IF EXISTS audit_trigger_metadata');
    }

    private function connectionName(): string
    {
        return $this->getConnection() ?? (string) config('database.default');
    }
};
