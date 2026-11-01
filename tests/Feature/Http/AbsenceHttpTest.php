<?php

namespace Tests\Feature\Http;

use App\Enums\AbsenceState;
use App\Enums\UserState;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
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
        // La décision prévient désormais le demandeur ; son message WhatsApp part par la file
        // (asynchrone en production), que ce test n'exécute pas.
        Queue::fake();
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

    /**
     * Décision du propriétaire du 2026-11-01 : sans responsable direct actif, tout compte direction
     * actif autre que le demandeur décide. Ce test encodait l'ancienne règle (personne ne pouvait
     * décider) ; il vérifie désormais que la direction ne s'approuve toujours pas elle-même.
     */
    public function test_ac_3_direction_cannot_self_approve_even_without_manager(): void
    {
        $direction = $this->userWithRole('direction');
        $directionAbsence = Absence::factory()->for($direction)->requested()->create();

        $this->actingAs($direction)->patch("/absences/{$directionAbsence->getKey()}/approuver")->assertForbidden();
        $this->assertSame(AbsenceState::Demandee, $directionAbsence->refresh()->state);
    }

    public function test_absence_without_manager_is_listed_and_decided_by_another_direction_account(): void
    {
        Queue::fake();
        $firstDirection = $this->userWithRole('direction');
        $secondDirection = $this->userWithRole('direction');
        $employee = $this->userWithRole('employe');
        $employeeAbsence = Absence::factory()->for($employee)->requested()->create();
        $directionAbsence = Absence::factory()->for($firstDirection)->requested()->create();

        $this->actingAs($secondDirection)->get('/absences')->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->has('pendingApprovals', 2)
                ->where('pendingApprovals.0.decision_route_label', 'Sans responsable direct : décision par la direction')
                ->where('pendingApprovals.0.can_approve', true)
                ->where('pendingApprovals.1.decision_route_label', 'Sans responsable direct : décision par la direction')
                ->where('pendingApprovals.1.can_approve', true),
        );
        $this->resetAuthentication();

        $this->actingAs($secondDirection)->patch("/absences/{$employeeAbsence->getKey()}/approuver")->assertRedirect();
        $this->actingAs($secondDirection)->patch("/absences/{$directionAbsence->getKey()}/refuser", [
            'decision_reason' => 'Réunion client ce jour-là',
        ])->assertRedirect();

        $this->assertSame(AbsenceState::Approuvee, $employeeAbsence->refresh()->state);
        $this->assertSame($secondDirection->getKey(), $employeeAbsence->decided_by);
        $this->assertSame(AbsenceState::Refusee, $directionAbsence->refresh()->state);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $employeeAbsence->getKey(),
            'action' => 'absence_approved',
            'actor_id' => $secondDirection->getKey(),
        ]);
    }

    public function test_absence_without_manager_cannot_be_decided_by_a_non_direction_account(): void
    {
        $employee = $this->userWithRole('employe');
        $colleague = $this->userWithRole('employe');
        $tutor = $this->userWithRole('tuteur');
        $absence = Absence::factory()->for($employee)->requested()->create();

        $this->actingAs($colleague)->patch("/absences/{$absence->getKey()}/approuver")->assertForbidden();
        $this->resetAuthentication();
        $this->actingAs($tutor)->patch("/absences/{$absence->getKey()}/refuser", ['decision_reason' => 'Non'])->assertForbidden();
        $this->resetAuthentication();
        $this->actingAs($colleague)->get('/absences')->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page->has('pendingApprovals', 0),
        );
        $this->assertSame(AbsenceState::Demandee, $absence->refresh()->state);
    }

    public function test_absence_with_an_active_manager_stays_reserved_to_that_manager_even_for_direction(): void
    {
        $manager = $this->userWithRole('tuteur');
        $employee = $this->userWithRole('employe', $manager);
        $direction = $this->userWithRole('direction');
        $absence = Absence::factory()->for($employee)->requested()->create();

        $this->actingAs($direction)->patch("/absences/{$absence->getKey()}/approuver")->assertForbidden();
        $this->resetAuthentication();
        $this->actingAs($direction)->get('/absences')->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page->has('pendingApprovals', 0),
        );
        $this->resetAuthentication();
        $this->actingAs($manager)->get("/absences/{$absence->getKey()}")->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('absence.decided_by_direction', false)
                ->where('absence.decision_route_label', null)
                ->where('absence.can_approve', true),
        );
        $this->assertSame(AbsenceState::Demandee, $absence->refresh()->state);
    }

    public function test_absence_whose_manager_is_no_longer_active_is_decided_by_direction(): void
    {
        Queue::fake();
        $manager = $this->userWithRole('tuteur');
        $employee = $this->userWithRole('employe', $manager);
        $direction = $this->userWithRole('direction');
        $manager->forceFill(['state' => UserState::Suspendu])->save();
        $absence = Absence::factory()->for($employee)->requested()->create();

        $this->actingAs($direction)->patch("/absences/{$absence->getKey()}/approuver")->assertRedirect();
        $this->assertSame(AbsenceState::Approuvee, $absence->refresh()->state);
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
