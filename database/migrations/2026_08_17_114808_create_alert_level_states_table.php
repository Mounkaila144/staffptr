<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trace du niveau d'alerte observé pour un mois (story 9.1 AC 5, 6, 7, 11).
 *
 * Le niveau reste **calculé, jamais saisi** : cette table ne fait qu'enregistrer le résultat du
 * dernier recalcul pour trois usages qu'un cache ne peut pas rendre.
 *
 * 1. `observed_at` date le moment où le mois est passé au niveau courant, ce qui donne son sens
 *    concret au délai de 48 heures du plan correctif en orange (AC 11).
 * 2. `calculated_at` rend le succès de la tâche planifiée observable par la supervision du
 *    jalon 11.4 (AC 7), sans journaliser aucune donnée personnelle.
 * 3. `source_date` fournit la date des données source affichée à côté du niveau (AC 6).
 *
 * Une ligne par mois, écrite en `updateOrCreate` : rejouer la tâche ne crée jamais de doublon.
 * `observed_at` n'est repoussé que lorsque le niveau change réellement, ce qui rend le recalcul
 * idempotent (AC 5, AC 35).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_level_states', function (Blueprint $table): void {
            $table->id();
            $table->date('month')->unique();
            $table->string('level', 20)->index();
            $table->unsignedBigInteger('baseline_amount')->default(0);
            $table->unsignedBigInteger('collections_amount')->default(0);
            $table->unsignedBigInteger('previous_collections_amount')->default(0);
            $table->date('source_date');
            $table->timestamp('observed_at', 3);
            $table->timestamp('calculated_at', 3);
            $table->timestamps(3);
        });

        $this->createDeleteTrigger();
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_delete_alert_level_states');
        Schema::dropIfExists('alert_level_states');
    }

    private function createDeleteTrigger(): void
    {
        $message = 'La suppression physique de alert_level_states est interdite.';

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared(
                "CREATE TRIGGER finance_no_delete_alert_level_states BEFORE DELETE ON alert_level_states FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'"
            );

            return;
        }

        DB::unprepared(
            "CREATE TRIGGER finance_no_delete_alert_level_states BEFORE DELETE ON alert_level_states BEGIN SELECT RAISE(ABORT, '{$message}'); END"
        );
    }
};
