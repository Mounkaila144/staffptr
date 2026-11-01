<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Décision du propriétaire du 2026-11-01 : le nombre d'approbations requises d'une dépense vaut
 * min(2, comptes direction actifs autres que le demandeur), au moins 1. Il est figé sur la
 * dépense au moment où elle devient approuvée, pour que le paiement et le contrôle d'invariants
 * relisent la règle effectivement appliquée. Les dépenses antérieures (NULL) en exigeaient deux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->unsignedTinyInteger('approvals_required')
                ->nullable()
                ->after('state')
                ->comment('Approbations exigées, figées au passage à approuvée ; NULL = règle historique (2)');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn('approvals_required');
        });
    }
};
