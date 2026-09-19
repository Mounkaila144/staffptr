<?php

namespace Tests\Feature;

use App\Enums\AbsenceState;
use App\Enums\AbsenceType;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Platform\AuditLog;
use App\Services\Identity\AbsenceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

class AbsenceLifecycleTest extends IdentityTestCase
{
    public function test_ac_1_and_2_declaration_with_optional_attachment_is_audited_and_starts_requested(): void
    {
        $user = User::factory()->active()->create();
        $attachment = Attachment::factory()->create([
            'attachable_type' => $user->person->getMorphClass(),
            'attachable_id' => $user->person_id,
            'uploaded_by' => $user->getKey(),
        ]);

        $absence = app(AbsenceService::class)->declare(
            $user,
            AbsenceType::Maladie,
            '2026-09-07',
            '2026-09-08',
            'Consultation médicale',
            $attachment,
            $user,
        );

        $this->assertSame(AbsenceState::Demandee, $absence->state);
        $this->assertSame($absence->getKey(), $attachment->refresh()->attachable_id);
        $this->assertSame($absence->getMorphClass(), $attachment->attachable_type);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Absence::class,
            'auditable_id' => $absence->getKey(),
            'actor_id' => $user->getKey(),
            'action' => 'absence_requested',
        ]);
    }

    public function test_ac_3_direct_manager_can_approve_and_the_transition_is_audited(): void
    {
        $manager = User::factory()->active()->create();
        $employee = User::factory()->active()->withManager($manager)->create();
        $absence = Absence::factory()->for($employee)->requested()->create();

        $approved = app(AbsenceService::class)->approve($absence, $manager);

        $this->assertSame(AbsenceState::Approuvee, $approved->state);
        $this->assertSame($manager->getKey(), $approved->decided_by);
        $this->assertNotNull($approved->decided_at);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Absence::class,
            'auditable_id' => $absence->getKey(),
            'action' => 'absence_approved',
        ]);
    }

    public function test_ac_2_refusal_requires_a_reason_and_only_a_requested_absence_can_transition(): void
    {
        $manager = User::factory()->active()->create();
        $employee = User::factory()->active()->withManager($manager)->create();
        $absence = Absence::factory()->for($employee)->requested()->create();

        try {
            app(AbsenceService::class)->refuse($absence, '  ', $manager);
            $this->fail('Un refus vide aurait dû être rejeté.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('decision_reason', $exception->errors());
        }

        $refused = app(AbsenceService::class)->refuse($absence, 'Présence nécessaire', $manager);
        $this->assertSame(AbsenceState::Refusee, $refused->state);
        $this->assertSame('Présence nécessaire', $refused->decision_reason);

        $this->expectException(ValidationException::class);
        app(AbsenceService::class)->approve($refused, $manager);
    }

    public function test_ac_3_self_unrelated_and_missing_manager_approval_are_forbidden(): void
    {
        $manager = User::factory()->active()->create();
        $employee = User::factory()->active()->withManager($manager)->create();
        $unrelated = User::factory()->active()->create();
        $withoutManager = User::factory()->active()->withoutManager()->create();
        $service = app(AbsenceService::class);

        foreach ([
            [Absence::factory()->for($employee)->requested()->create(), $employee],
            [Absence::factory()->for($employee)->requested()->create(), $unrelated],
            [Absence::factory()->for($withoutManager)->requested()->create(), $manager],
        ] as [$absence, $actor]) {
            try {
                $service->approve($absence, $actor);
                $this->fail("L'approbation non autorisée aurait dû être rejetée.");
            } catch (AuthorizationException) {
                $this->assertSame(AbsenceState::Demandee, $absence->refresh()->state);
            }
        }
    }

    public function test_ac_2_requester_can_cancel_a_requested_absence(): void
    {
        $user = User::factory()->active()->create();
        $absence = Absence::factory()->for($user)->requested()->create();

        $cancelled = app(AbsenceService::class)->cancel($absence, $user);

        $this->assertSame(AbsenceState::Annulee, $cancelled->state);
        $this->assertSame(1, AuditLog::query()->where('auditable_type', Absence::class)->where('action', 'absence_cancelled')->count());
    }
}
