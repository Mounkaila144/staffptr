<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reserve_movements', function (Blueprint $table): void {
            $table->string('approval_state', 20)->default('approved')->index()->after('type');
            $table->timestamp('requested_at', 3)->nullable()->after('idempotency_key');
            $table->timestamp('approved_at', 3)->nullable()->after('requested_at');
        });

        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_update_reserve_movements');
        $this->createApprovedImmutableTrigger();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_update_reserve_movements');
        Schema::table('reserve_movements', function (Blueprint $table): void {
            $table->dropColumn(['approval_state', 'requested_at', 'approved_at']);
        });
        $this->createFullyImmutableTrigger();
    }

    private function createApprovedImmutableTrigger(): void
    {
        $message = 'Une écriture approuvée de reserve_movements est immuable.';
        if ($this->isMysqlFamily()) {
            DB::unprepared("CREATE TRIGGER finance_no_update_reserve_movements BEFORE UPDATE ON reserve_movements FOR EACH ROW BEGIN IF OLD.approval_state = 'approved' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END");

            return;
        }
        DB::unprepared("CREATE TRIGGER finance_no_update_reserve_movements BEFORE UPDATE ON reserve_movements WHEN OLD.approval_state = 'approved' BEGIN SELECT RAISE(ABORT, '{$message}'); END");
    }

    private function createFullyImmutableTrigger(): void
    {
        $message = 'Une écriture validée de reserve_movements est immuable.';
        if ($this->isMysqlFamily()) {
            DB::unprepared("CREATE TRIGGER finance_no_update_reserve_movements BEFORE UPDATE ON reserve_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'");

            return;
        }
        DB::unprepared("CREATE TRIGGER finance_no_update_reserve_movements BEFORE UPDATE ON reserve_movements BEGIN SELECT RAISE(ABORT, '{$message}'); END");
    }

    private function isMysqlFamily(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
