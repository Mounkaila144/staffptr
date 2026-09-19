<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Filtres enregistrés, privés à leur auteur (story 10.1 AC 8).
 *
 * L'architecture ne définissait ni modèle ni module pour eux. Deux choix structurent la table.
 *
 * 1. **`owner_id` est le seul mécanisme de partage — c'est-à-dire aucun.** Un filtre appartient à
 *    son auteur et n'est lisible que par lui. Il n'existe volontairement pas de colonne « visible
 *    par », parce qu'un filtre partagé deviendrait un canal de fuite : son seul contenu décrit
 *    déjà ce que son auteur a le droit de chercher.
 * 2. **`criteria` ne stocke que des critères, jamais une permission ni un identifiant de portée.**
 *    Rejouer un filtre repasse intégralement par la Policy et le scope de la liste : le filtre
 *    enregistré ne peut donc pas devenir une autorisation dormante (AC 9, PERM-06).
 *
 * `list_key` désigne la liste concernée parmi l'inventaire figé en Task 1 ; l'unicité
 * `(owner_id, list_key, name)` empêche deux filtres homonymes sur une même liste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_filters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('list_key', 60)->index();
            $table->string('name', 120);
            $table->json('criteria');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('deactivated_at', 3)->nullable();
            $table->timestamps(3);
            $table->unique(['owner_id', 'list_key', 'name']);
        });

        $this->createDeleteTrigger();
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS platform_no_delete_saved_filters');
        Schema::dropIfExists('saved_filters');
    }

    /**
     * SOC-03 : aucune suppression physique. Retirer un filtre le désactive ; la ligne reste, ce
     * qui garde l'historique d'audit interprétable.
     */
    private function createDeleteTrigger(): void
    {
        $message = 'La suppression physique de saved_filters est interdite.';

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared(
                "CREATE TRIGGER platform_no_delete_saved_filters BEFORE DELETE ON saved_filters FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'"
            );

            return;
        }

        DB::unprepared(
            "CREATE TRIGGER platform_no_delete_saved_filters BEFORE DELETE ON saved_filters BEGIN SELECT RAISE(ABORT, '{$message}'); END"
        );
    }
};
