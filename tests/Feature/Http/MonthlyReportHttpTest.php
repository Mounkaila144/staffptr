<?php

namespace Tests\Feature\Http;

use App\Models\Finance\MonthlyReport;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
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

    /** L'écran nomme qui a préparé, contrôlé et validé, et traduit l'état et le niveau d'alerte. */
    public function test_the_listing_names_each_step_author_and_translates_state_and_alert_level(): void
    {
        $preparer = $this->roleUser('finance');
        $controller = $this->roleUser('finance');
        $direction = $this->roleUser('direction');
        $this->actingAs($preparer)->post(route('financial-reports.store'), ['month' => '2026-07']);
        $report = MonthlyReport::query()->sole();
        $this->resetAuthentication();
        $this->actingAs($controller)->patch(route('financial-reports.control', $report));
        $this->resetAuthentication();
        $this->actingAs($direction)->patch(route('financial-reports.validate', $report));

        $this->actingAs($direction)->get(route('financial-reports.index'))->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload
                ->where('reports.0.state_label', 'Validé et clôturé')
                ->where('reports.0.prepared_by_name', $preparer->person->full_name)
                ->where('reports.0.controlled_by_name', $controller->person->full_name)
                ->where('reports.0.validated_by_name', $direction->person->full_name)
                ->where('reports.0.alert_level_label', fn (?string $label): bool => in_array($label, ['Vert', 'Orange', 'Rouge'], true))),
        );
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
