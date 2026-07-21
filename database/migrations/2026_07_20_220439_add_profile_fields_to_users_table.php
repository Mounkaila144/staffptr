<?php

use App\Enums\RelationType;
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
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('job_function_id')
                ->nullable()
                ->after('department_id')
                ->constrained('job_functions')
                ->restrictOnDelete();
            $table->foreignId('manager_id')
                ->nullable()
                ->after('job_function_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('relation_type', array_column(RelationType::cases(), 'value'))
                ->default(RelationType::Employe->value)
                ->after('manager_id');
            $table->date('contract_start_date')->nullable()->after('relation_type');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('job_function_id');
            $table->dropColumn(['relation_type', 'contract_start_date', 'contract_end_date']);
        });
    }
};
