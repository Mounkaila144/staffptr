<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_sequences', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('consumed_at', 3);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('receipt_sequence_id')->nullable()->unique()->after('id')->constrained('receipt_sequences')->restrictOnDelete();
            $table->timestamp('received_at', 3)->nullable()->after('received_on');
        });

        Schema::table('share_entitlements', function (Blueprint $table): void {
            $table->timestamp('reversed_at', 3)->nullable()->after('paid_amount');
            $table->foreignId('reversal_payment_id')->nullable()->after('reversed_at')->constrained('payments')->restrictOnDelete();
        });

        $this->createNoDeleteTrigger();
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_delete_receipt_sequences');
        Schema::table('share_entitlements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversal_payment_id');
            $table->dropColumn('reversed_at');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('receipt_sequence_id');
            $table->dropColumn('received_at');
        });
        Schema::dropIfExists('receipt_sequences');
    }

    private function createNoDeleteTrigger(): void
    {
        $message = 'La suppression physique de receipt_sequences est interdite.';
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER finance_no_delete_receipt_sequences BEFORE DELETE ON receipt_sequences FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'");

            return;
        }

        DB::unprepared("CREATE TRIGGER finance_no_delete_receipt_sequences BEFORE DELETE ON receipt_sequences BEGIN SELECT RAISE(ABORT, '{$message}'); END");
    }
};
