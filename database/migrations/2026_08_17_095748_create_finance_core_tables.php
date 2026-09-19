<?php

use App\Enums\ContractState;
use App\Enums\FinancialAccountState;
use App\Enums\FinancialAccountType;
use App\Enums\FinancialMovementDirection;
use App\Enums\InvoiceState;
use App\Enums\MonthlyReportState;
use App\Enums\PaymentState;
use App\Enums\ReconciliationState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30)->default(FinancialAccountType::Caisse->value);
            $table->string('label', 120)->unique();
            $table->unsignedBigInteger('opening_balance_amount')->default(0);
            $table->date('opening_balance_date');
            $table->string('state', 20)->default(FinancialAccountState::Active->value)->index();
            $table->text('deactivation_reason')->nullable();
            $table->timestamp('deactivated_at', 3)->nullable();
            $table->timestamps(3);
        });

        Schema::create('fixed_charges', function (Blueprint $table): void {
            $table->id();
            $table->string('label', 120)->unique();
            $table->unsignedBigInteger('monthly_amount')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps(3);
        });

        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160)->index();
            $table->string('phone', 32)->unique();
            $table->string('contact', 160)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps(3);
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->string('reference', 80)->unique();
            $table->string('title', 200);
            $table->unsignedBigInteger('expected_total_amount');
            $table->unsignedBigInteger('forecast_profit_amount');
            $table->foreignId('contributor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('has_execution')->default(false);
            $table->string('state', 20)->default(ContractState::Active->value)->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('closed_at', 3)->nullable();
            $table->text('closure_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps(3);
        });

        Schema::create('contract_executors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('deactivated_at', 3)->nullable();
            $table->timestamps(3);
            $table->index(['contract_id', 'is_active', 'position']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->string('number', 80)->unique();
            $table->unsignedBigInteger('total_amount');
            $table->date('issued_on');
            $table->date('due_on')->index();
            $table->string('state', 30)->default(InvoiceState::Impayee->value)->index();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at', 3)->nullable();
            $table->timestamps(3);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_number', 80)->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedBigInteger('received_amount');
            $table->date('received_on')->index();
            $table->string('payment_mode', 30);
            $table->string('reference', 160)->nullable();
            $table->string('state', 20)->default(PaymentState::Validated->value)->index();
            $table->foreignId('correction_of_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('late_recording')->default(false)->index();
            $table->boolean('post_reopening')->default(false);
            $table->ulid('idempotency_key')->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps(3);
        });

        Schema::create('account_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('direction', 10)->default(FinancialMovementDirection::Credit->value);
            $table->unsignedBigInteger('movement_amount');
            $table->date('effective_on')->index();
            $table->string('source_type', 160);
            $table->unsignedBigInteger('source_id');
            $table->text('description');
            $table->foreignId('reversal_of_id')->nullable()->constrained('account_movements')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('validated_at', 3);
            $table->timestamps(3);
            $table->index(['source_type', 'source_id']);
            $table->unique(['source_type', 'source_id', 'direction']);
        });

        Schema::create('share_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('beneficiary_key', 80);
            $table->string('share_type', 30);
            $table->unsignedBigInteger('base_amount');
            $table->unsignedSmallInteger('rate_basis_points');
            $table->unsignedSmallInteger('rate_divisor')->default(1);
            $table->unsignedBigInteger('share_amount');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('calculation_method', 255);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('source_received_on');
            $table->timestamps(3);
            $table->unique(['payment_id', 'beneficiary_key', 'share_type'], 'share_entitlements_source_unique');
        });

        Schema::create('reserve_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30);
            $table->unsignedBigInteger('movement_amount');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('reserve_movements')->restrictOnDelete();
            $table->date('occurred_on')->index();
            $table->text('reason')->nullable();
            $table->text('reconstitution_plan')->nullable();
            $table->foreignId('first_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('second_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->ulid('idempotency_key')->unique();
            $table->timestamps(3);
        });

        Schema::create('reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end')->index();
            $table->unsignedBigInteger('calculated_balance_amount');
            $table->unsignedBigInteger('physical_balance_amount');
            $table->string('difference_direction', 10)->nullable();
            $table->unsignedBigInteger('difference_amount')->default(0);
            $table->text('difference_explanation')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('corrective_action')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('controlled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('state', 20)->default(ReconciliationState::Draft->value)->index();
            $table->timestamp('validated_at', 3)->nullable();
            $table->foreignId('previous_id')->nullable()->constrained('reconciliations')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->timestamps(3);
            $table->unique(['account_id', 'period_start', 'period_end', 'prepared_by'], 'reconciliation_period_unique');
        });

        Schema::create('monthly_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->date('month');
            $table->unsignedBigInteger('budget_amount');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps(3);
            $table->unique(['category_id', 'month']);
        });

        Schema::create('monthly_reports', function (Blueprint $table): void {
            $table->id();
            $table->date('month')->unique();
            $table->string('state', 20)->default(MonthlyReportState::Draft->value)->index();
            $table->json('lines');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('controlled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('controlled_at', 3)->nullable();
            $table->timestamp('validated_at', 3)->nullable();
            $table->string('alert_level', 20)->nullable();
            $table->date('alert_source_date')->nullable();
            $table->timestamps(3);
        });

        Schema::create('month_closures', function (Blueprint $table): void {
            $table->id();
            $table->date('month')->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('monthly_report_id')->nullable()->constrained('monthly_reports')->restrictOnDelete();
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at', 3);
            $table->foreignId('reopened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reopened_at', 3)->nullable();
            $table->text('reopen_reason')->nullable();
            $table->string('frozen_alert_level', 20)->nullable();
            $table->timestamps(3);
            $table->unique(['month', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('month_closures');
        Schema::dropIfExists('monthly_reports');
        Schema::dropIfExists('monthly_budgets');
        Schema::dropIfExists('reconciliations');
        Schema::dropIfExists('reserve_movements');
        Schema::dropIfExists('share_entitlements');
        Schema::dropIfExists('account_movements');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('contract_executors');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('fixed_charges');
        Schema::dropIfExists('accounts');
    }
};
