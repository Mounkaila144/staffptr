<?php

namespace Tests\Feature\Http;

use App\Models\Finance\Account;
use App\Models\Finance\Reconciliation;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Tests\Support\IdentityTestCase;

class ReconciliationHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_two_financial_accounts_prepare_and_control_while_employee_is_forbidden(): void
    {
        $account = Account::factory()->create(['opening_balance_amount' => 100_000]);
        $preparer = $this->roleUser('finance');
        $controller = $this->roleUser('finance');
        $this->actingAs($preparer)->post(route('reconciliations.store'), [
            'account_id' => $account->getKey(), 'period_start' => '2026-08-03', 'period_end' => '2026-08-09',
            'physical_balance_amount' => 100_000, 'difference_explanation' => null, 'responsible_id' => null, 'corrective_action' => null,
        ])->assertRedirect(route('reconciliations.index'));
        $item = Reconciliation::query()->sole();
        $this->resetAuthentication();
        $this->actingAs($controller)->patch(route('reconciliations.validate', $item))->assertRedirect(route('reconciliations.index'));
        $this->assertNotNull($item->refresh()->validated_at);
        $this->resetAuthentication();
        $this->actingAs($this->roleUser('employe'))->get(route('reconciliations.index'))->assertForbidden();
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test rapprochement story 8.1');

        return $user;
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
