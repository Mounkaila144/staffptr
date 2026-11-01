<?php

namespace Tests\Feature\Http;

use App\Models\Finance\ReserveMovement;
use App\Models\Identity\User;
use App\Services\Finance\ReserveService;
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

    /** Le bouton « Approuver » n'est proposé qu'au directeur qui peut encore approuver. */
    public function test_each_pending_usage_tells_the_viewer_whether_they_can_still_approve_it(): void
    {
        ReserveMovement::factory()->create(['movement_amount' => 500_000, 'approval_state' => 'approved']);
        $finance = $this->roleUser('finance');
        $first = $this->roleUser('direction');
        $second = $this->roleUser('direction');
        $this->actingAs($finance)->post(route('reserve.usages.store'), ['movement_amount' => 50_000, 'reason' => 'Incident.', 'reconstitution_plan' => 'Contrats suivants.', 'idempotency_key' => (string) Str::ulid()]);
        $usage = ReserveMovement::query()->where('type', 'usage')->sole();
        $this->resetAuthentication();
        $this->actingAs($first)->patch(route('reserve.usages.approve', $usage));

        $canApprove = function (User $viewer) use ($usage): bool {
            $movements = app(ReserveService::class)->summary($viewer)['movements'];
            $this->assertIsArray($movements);

            foreach ($movements as $movement) {
                if (is_array($movement) && $movement['id'] === $usage->getKey()) {
                    return (bool) $movement['can_approve'];
                }
            }

            $this->fail('La demande d’utilisation n’apparaît pas dans le livre.');
        };

        $this->assertFalse($canApprove($finance), 'Le demandeur n’approuve pas.');
        $this->assertFalse($canApprove($first), 'Le premier approbateur ne peut pas approuver deux fois.');
        $this->assertTrue($canApprove($second));
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
