<?php

namespace Tests\Feature\Http;

use App\Enums\AbsenceState;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class AbsenceHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ac_1_user_can_declare_an_absence_with_an_optional_staged_attachment(): void
    {
        $employee = $this->userWithRole('employe');
        $attachment = Attachment::factory()->create([
            'attachable_type' => $employee->person->getMorphClass(),
            'attachable_id' => $employee->person_id,
            'uploaded_by' => $employee->getKey(),
        ]);

        $response = $this->actingAs($employee)->post('/absences', [
            'type' => 'maladie',
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
            'reason' => 'Consultation médicale',
            'attachment_ulid' => $attachment->ulid,
        ]);

        $absence = Absence::query()->where('user_id', $employee->getKey())->sole();
        $response->assertRedirect(route('absences.show', $absence));
        $this->assertSame(AbsenceState::Demandee, $absence->state);
        $this->assertSame($absence->getKey(), $attachment->refresh()->attachable_id);
    }

    public function test_ac_1_declaration_validates_type_dates_reason_and_attachment(): void
    {
        $employee = $this->userWithRole('employe');

        $this->actingAs($employee)->post('/absences', [
            'type' => 'inconnu',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-09',
            'reason' => '',
            'attachment_ulid' => 'invalide',
        ])->assertSessionHasErrors(['type', 'end_date', 'reason', 'attachment_ulid']);
    }

    public function test_ac_3_direct_manager_can_approve_or_refuse_but_others_cannot_by_direct_url(): void
    {
        $manager = $this->userWithRole('tuteur');
        $employee = $this->userWithRole('employe', $manager);
        $other = $this->userWithRole('finance');
        $absence = Absence::factory()->for($employee)->requested()->create();

        $this->actingAs($other)->patch("/absences/{$absence->getKey()}/approuver")->assertForbidden();
        $this->resetAuthentication();
        $this->actingAs($employee)->patch("/absences/{$absence->getKey()}/approuver")->assertForbidden();
        $this->resetAuthentication();
        $this->actingAs($manager)->patch("/absences/{$absence->getKey()}/refuser", [
            'decision_reason' => 'Période de forte activité',
        ])->assertRedirect();

        $this->assertSame(AbsenceState::Refusee, $absence->refresh()->state);
        $this->assertSame('Période de forte activité', $absence->decision_reason);
    }

    public function test_ac_7_visibility_is_limited_to_self_direct_team_and_direction(): void
    {
        $manager = $this->userWithRole('tuteur');
        $employee = $this->userWithRole('employe', $manager);
        $other = $this->userWithRole('finance');
        $direction = $this->userWithRole('direction');
        $absence = Absence::factory()->for($employee)->requested()->create();

        $this->actingAs($employee)->get("/absences/{$absence->getKey()}")->assertOk();
        $this->resetAuthentication();
        $this->actingAs($manager)->get("/absences/{$absence->getKey()}")->assertOk();
        $this->resetAuthentication();
        $this->actingAs($direction)->get("/absences/{$absence->getKey()}")->assertOk();
        $this->resetAuthentication();
        $this->actingAs($other)->get("/absences/{$absence->getKey()}")->assertForbidden();
    }

    public function test_ac_7_and_8_index_exposes_distinct_personal_approval_and_visible_zones(): void
    {
        $manager = $this->userWithRole('tuteur');
        $employee = $this->userWithRole('employe', $manager);
        Absence::factory()->for($manager)->requested()->create();
        Absence::factory()->for($employee)->requested()->create();

        $this->actingAs($manager)->get('/absences')->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Identity/Absences/Index')
                ->has('myAbsences', 1)
                ->has('pendingApprovals', 1)
                ->has('visibleAbsences', 2)
                ->has('types', 3)
                ->where('attachment.attachable_id', $manager->person_id),
        );
    }

    public function test_ac_3_direction_cannot_self_approve_and_user_without_manager_cannot_be_approved(): void
    {
        $employee = $this->userWithRole('employe');
        $direction = $this->userWithRole('direction');
        $absence = Absence::factory()->for($employee)->requested()->create();
        $directionAbsence = Absence::factory()->for($direction)->requested()->create();

        $this->actingAs($direction)->patch("/absences/{$absence->getKey()}/approuver")->assertForbidden();
        $this->actingAs($direction)->patch("/absences/{$directionAbsence->getKey()}/approuver")->assertForbidden();
        $this->assertSame(AbsenceState::Demandee, $absence->refresh()->state);
        $this->assertSame(AbsenceState::Demandee, $directionAbsence->refresh()->state);
    }

    private function userWithRole(string $role, ?User $manager = null): User
    {
        $user = User::factory()->active()->create(['manager_id' => $manager?->getKey()]);
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP story 4.2');

        return $user;
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
