<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Décision du propriétaire du 2026-11-01 : une utilisation de réserve demandée par un dirigeant
 * est approuvée par l'autre dirigeant seul quand l'entreprise n'en compte que deux. Le nombre
 * d'approbations exigé est figé au passage à « approved » ; NULL = règle historique (2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reserve_movements', function (Blueprint $table): void {
            $table->unsignedTinyInteger('approvals_required')
                ->nullable()
                ->after('approval_state')
                ->comment('Approbations exigées, figées au passage à approved ; NULL = règle historique (2)');
        });
    }

    public function down(): void
    {
        Schema::table('reserve_movements', function (Blueprint $table): void {
            $table->dropColumn('approvals_required');
        });
    }
};
