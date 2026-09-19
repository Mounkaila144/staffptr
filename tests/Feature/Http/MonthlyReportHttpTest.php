<?php

namespace Tests\Feature\Http;

use App\Models\Finance\MonthlyReport;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Tests\Support\IdentityTestCase;

class MonthlyReportHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_finance_prepares_and_controls_then_direction_validates_and_reopens(): void
    {
        $preparer = $this->roleUser('finance');
        $controller = $this->roleUser('finance');
        $direction = $this->roleUser('direction');
        $this->actingAs($preparer)->post(route('financial-reports.store'), ['month' => '2026-07'])->assertRedirect(route('financial-reports.index'));
        $report = MonthlyReport::query()->sole();
        $this->resetAuthentication();
        $this->actingAs($controller)->patch(route('financial-reports.control', $report))->assertRedirect(route('financial-reports.index'));
        $this->resetAuthentication();
        $this->actingAs($direction)->patch(route('financial-reports.validate', $report))->assertRedirect(route('financial-reports.index'));
        $this->actingAs($direction)->patch(route('financial-reports.reopen', $report), ['reason' => 'Correction justifiée.'])->assertRedirect(route('financial-reports.index'));
        $this->resetAuthentication();
        $this->actingAs($this->roleUser('stagiaire'))->get(route('financial-reports.index'))->assertForbidden();
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test rapport HTTP story 8.1');

        return $user;
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
