<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table): void {
            $table->dropUnique('monthly_reports_month_unique');
            $table->unsignedInteger('version')->default(1)->after('month');
            $table->foreignId('previous_id')->nullable()->after('version')->constrained('monthly_reports')->restrictOnDelete();
            $table->unique(['month', 'version'], 'monthly_reports_month_version_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table): void {
            $table->dropUnique('monthly_reports_month_version_unique');
            $table->dropConstrainedForeignId('previous_id');
            $table->dropColumn('version');
            $table->unique('month');
        });
    }
};
