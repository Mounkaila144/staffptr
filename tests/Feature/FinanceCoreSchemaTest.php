<?php

namespace Tests\Feature;

use App\Models\Finance\Account;
use App\Models\Finance\AccountMovement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceCoreSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ac_1_task_1_finance_tables_and_required_columns_exist(): void
    {
        $expectedTables = [
            'accounts',
            'fixed_charges',
            'clients',
            'contracts',
            'contract_executors',
            'invoices',
            'payments',
            'account_movements',
            'share_entitlements',
            'reserve_movements',
            'reconciliations',
            'monthly_budgets',
            'monthly_reports',
            'month_closures',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "La table {$table} doit exister.");
        }

        $this->assertTrue(Schema::hasColumns('accounts', [
            'type', 'label', 'opening_balance_amount', 'opening_balance_date', 'state',
        ]));
        $this->assertTrue(Schema::hasColumns('contracts', [
            'client_id', 'project_id', 'expected_total_amount', 'forecast_profit_amount',
            'contributor_id', 'has_execution',
        ]));
        $this->assertTrue(Schema::hasColumns('payments', [
            'receipt_number', 'received_amount', 'received_on', 'account_id', 'idempotency_key',
            'correction_of_id', 'reversal_of_id',
        ]));
        $this->assertTrue(Schema::hasColumns('expenses', [
            'account_id', 'paid_on', 'contract_id', 'project_id', 'share_entitlement_id',
            'counter_entry_of_id', 'payment_idempotency_key',
        ]));
    }

    public function test_ac_94_financial_rows_cannot_be_physically_deleted_at_database_level(): void
    {
        $account = Account::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('accounts')->where('id', $account->getKey())->delete();
    }

    public function test_ac_94_validated_ledger_movements_are_immutable_at_database_level(): void
    {
        $movement = AccountMovement::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('account_movements')
            ->where('id', $movement->getKey())
            ->update(['movement_amount' => 1]);
    }
}
