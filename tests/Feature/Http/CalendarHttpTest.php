<?php

namespace Tests\Feature\Http;

use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Models\Platform\Holiday;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use LogicException;
use Tests\Support\IdentityTestCase;

class CalendarHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_2_direction_can_create_and_edit_a_holiday_with_a_label_and_effective_date(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)
            ->post('/calendrier/jours-feries', [
                'label' => 'Fermeture exceptionnelle',
                'date' => '2026-09-14',
                'calendar_year' => 2026,
                'calendar_month' => 9,
            ])
            ->assertRedirect(route('calendar.index', ['year' => 2026, 'month' => 9]));

        $holiday = Holiday::query()->where('date', '2026-09-14')->sole();
        $this->actingAs($direction)
            ->patch("/calendrier/jours-feries/{$holiday->getKey()}", [
                'label' => 'Fermeture annuelle',
                'date' => '2026-09-15',
            ])
            ->assertRedirect(route('calendar.index'));

        $this->assertDatabaseHas('holidays', [
            'id' => $holiday->getKey(),
            'label' => 'Fermeture annuelle',
            'date' => '2026-09-15',
            'is_active' => true,
        ]);
    }

    public function test_ac_5_calendar_changes_are_audited_and_physical_deletion_is_impossible(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)->post('/calendrier/jours-feries', [
            'label' => 'Fermeture auditée',
            'date' => '2026-10-05',
        ])->assertRedirect();
        $holiday = Holiday::query()->where('date', '2026-10-05')->sole();

        $this->actingAs($direction)
            ->patch("/calendrier/jours-feries/{$holiday->getKey()}/desactiver")
            ->assertRedirect();
        $this->actingAs($direction)
            ->patch("/calendrier/jours-feries/{$holiday->getKey()}/reactiver")
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            ['holiday_created', 'holiday_deactivated', 'holiday_reactivated'],
            AuditLog::query()
                ->where('auditable_type', Holiday::class)
                ->where('auditable_id', $holiday->getKey())
                ->pluck('action')
                ->all(),
        );
        $this->assertSame(
            3,
            AuditLog::query()->where('auditable_type', Holiday::class)->where('actor_id', $direction->getKey())->count(),
        );
        $this->assertFalse(Route::has('holidays.destroy'));

        $this->expectException(LogicException::class);
        $holiday->delete();
    }

    public function test_ac_5_only_direction_can_open_or_change_the_calendar_by_direct_url(): void
    {
        $holiday = Holiday::factory()->create(['date' => '2026-11-02']);
        $requests = [
            ['GET', '/calendrier', []],
            ['POST', '/calendrier/jours-feries', ['label' => 'Interdit', 'date' => '2026-11-03']],
            ['PATCH', "/calendrier/jours-feries/{$holiday->getKey()}", ['label' => 'Interdit', 'date' => '2026-11-04']],
            ['PATCH', "/calendrier/jours-feries/{$holiday->getKey()}/desactiver", []],
            ['PATCH', "/calendrier/jours-feries/{$holiday->getKey()}/reactiver", []],
        ];

        foreach (['super_admin', 'finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            Auth::forgetGuards();
            $user = $this->userWithRole($role);

            foreach ($requests as [$method, $path, $payload]) {
                $this->assertSame(403, $this->actingAs($user)->call($method, $path, $payload)->getStatusCode());
            }
        }
    }

    public function test_ac_6_calendar_exposes_text_labels_for_non_working_days_and_an_actionable_empty_state(): void
    {
        $direction = $this->userWithRole('direction');
        Holiday::factory()->create(['label' => 'Journée test', 'date' => '2026-09-14']);

        $this->actingAs($direction)
            ->get('/calendrier?year=2026&month=9')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Platform/Calendar/Index')
                ->where('period.label', 'Septembre 2026')
                ->where('days.13.status_label', 'Férié : Journée test')
                ->where('days.18.status_label', 'Week-end')
                ->has('workingDays', 5)
                ->has('holidays', 1));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP story 4.1');

        return $user;
    }
}
