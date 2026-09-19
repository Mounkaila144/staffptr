<?php

namespace Tests\Feature\Http;

use App\Models\Finance\ReserveMovement;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Support\IdentityTestCase;

class ReserveHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_finance_requests_usage_direction_approves_and_employee_sees_nothing(): void
    {
        ReserveMovement::factory()->create(['movement_amount' => 500_000, 'approval_state' => 'approved']);
        $finance = $this->roleUser('finance');
        $direction = $this->roleUser('direction');
        $payload = ['movement_amount' => 50_000, 'reason' => 'Incident matériel.', 'reconstitution_plan' => 'Deux prochains contrats.', 'idempotency_key' => (string) Str::ulid()];

        $this->actingAs($finance)->post(route('reserve.usages.store'), $payload)->assertRedirect(route('reserve.index'));
        $usage = ReserveMovement::query()->where('type', 'usage')->sole();
        $this->resetAuthentication();
        $this->actingAs($direction)->patch(route('reserve.usages.approve', $usage))->assertRedirect(route('reserve.index'));
        $this->assertNotNull($usage->refresh()->first_approved_by);
        $this->resetAuthentication();
        $this->actingAs($this->roleUser('employe'))->get(route('reserve.index'))->assertForbidden();
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test réserve HTTP story 8.1');

        return $user;
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
