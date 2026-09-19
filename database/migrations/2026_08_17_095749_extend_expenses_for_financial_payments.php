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
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('account_id')->nullable()->after('state')->constrained('accounts')->restrictOnDelete();
            $table->date('paid_on')->nullable()->after('account_id')->index();
            $table->string('payment_mode', 30)->nullable()->after('paid_on');
            $table->string('payment_reference', 160)->nullable()->after('payment_mode');
            $table->foreignId('paid_by')->nullable()->after('payment_reference')->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at', 3)->nullable()->after('paid_by');
            $table->foreignId('project_id')->nullable()->after('paid_at')->constrained('projects')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->after('project_id')->constrained('contracts')->restrictOnDelete();
            $table->foreignId('beneficiary_user_id')->nullable()->after('contract_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('share_entitlement_id')->nullable()->after('beneficiary_user_id')->constrained('share_entitlements')->restrictOnDelete();
            $table->foreignId('payment_attachment_id')->nullable()->after('share_entitlement_id')->constrained('attachments')->restrictOnDelete();
            $table->foreignId('original_attachment_id')->nullable()->after('payment_attachment_id')->constrained('attachments')->restrictOnDelete();
            $table->boolean('is_advance_reimbursement')->default(false)->after('original_attachment_id');
            $table->foreignId('counter_entry_of_id')->nullable()->after('is_advance_reimbursement')->constrained('expenses')->restrictOnDelete();
            $table->boolean('post_reopening')->default(false)->after('counter_entry_of_id');
            $table->ulid('payment_idempotency_key')->nullable()->after('post_reopening')->unique();
            $table->text('payment_cancellation_reason')->nullable()->after('payment_idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('account_id');
            $table->dropConstrainedForeignId('paid_by');
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('contract_id');
            $table->dropConstrainedForeignId('beneficiary_user_id');
            $table->dropConstrainedForeignId('share_entitlement_id');
            $table->dropConstrainedForeignId('payment_attachment_id');
            $table->dropConstrainedForeignId('original_attachment_id');
            $table->dropConstrainedForeignId('counter_entry_of_id');
            $table->dropColumn([
                'paid_on',
                'payment_mode',
                'payment_reference',
                'paid_at',
                'is_advance_reimbursement',
                'post_reopening',
                'payment_idempotency_key',
                'payment_cancellation_reason',
            ]);
        });
    }
};
