<?php

namespace Tests\Feature;

use App\Enums\InternshipChecklistType;
use App\Enums\InternshipEvaluationType;
use App\Enums\InternshipIntakeState;
use App\Enums\InternshipState;
use App\Enums\ReviewObjectiveStatus;
use App\Enums\WeeklyReviewState;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\ImprovementPlan;
use App\Models\Accountability\ImprovementPlanAction;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipChecklistItem;
use App\Models\Accountability\InternshipEvaluation;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Accountability\InternshipPlan;
use App\Models\Accountability\SupportRequestBatch;
use App\Models\Accountability\SupportRequestBatchItem;
use App\Models\Accountability\TutorSupportSlot;
use App\Models\Accountability\WeeklyReview;
use App\Models\Accountability\WeeklyReviewObjective;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Task 1 — socle de données de l'Epic 7 : identités, relations, états typés,
 * dates civiles de Niamey et interdiction de suppression physique.
 */
class InternshipAndReviewSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_person_has_at_most_one_review_per_week(): void
    {
        $subject = User::factory()->create();
        WeeklyReview::factory()->create([
            'subject_user_id' => $subject->getKey(),
            'week_start_date' => '2026-08-10',
        ]);

        $this->expectException(QueryException::class);

        WeeklyReview::factory()->create([
            'subject_user_id' => $subject->getKey(),
            'week_start_date' => '2026-08-10',
        ]);
    }

    public function test_review_defaults_to_friday_and_carries_both_nominative_validations(): void
    {
        $subject = User::factory()->create();
        $reviewer = User::factory()->create();
        $review = WeeklyReview::factory()->validated()->create([
            'subject_user_id' => $subject->getKey(),
            'reviewer_id' => $reviewer->getKey(),
            'week_start_date' => '2026-08-10',
            'scheduled_on' => '2026-08-14',
        ]);

        // Vendredi de la semaine du 10 août 2026 (AC 1).
        $this->assertSame('vendredi', $review->scheduled_on->locale('fr')->dayName);
        $this->assertTrue($review->hasBothValidations());
        $this->assertSame($subject->getKey(), $review->reviewee_validated_by);
        $this->assertSame($reviewer->getKey(), $review->reviewer_validated_by);
        $this->assertNotNull($review->reviewee_validated_at);
        $this->assertNotNull($review->reviewer_validated_at);
        $this->assertSame(WeeklyReviewState::Validee, $review->state);
        $this->assertFalse($review->isEditable());
    }

    public function test_review_aggregates_objectives_with_result_evidence_status_gap_and_next_action(): void
    {
        $review = WeeklyReview::factory()->create();
        $objective = Objective::factory()->create();
        $entry = WeeklyReviewObjective::factory()->withGap()->create([
            'weekly_review_id' => $review->getKey(),
            'objective_id' => $objective->getKey(),
        ]);

        $this->assertSame(1, $review->objectiveEntries()->count());
        $this->assertSame(ReviewObjectiveStatus::PartiellementAtteint, $entry->status);
        $this->assertTrue($entry->status->requiresGapCause());
        $this->assertNotNull($entry->gap_cause);
        $this->assertNotNull($entry->result);
        $this->assertNotNull($entry->next_action);
        $this->assertTrue($objective->is($entry->objective));
    }

    public function test_review_is_visible_to_subject_reviewer_and_direction_only(): void
    {
        $subject = User::factory()->create();
        $reviewer = User::factory()->create();
        $peer = User::factory()->create();
        WeeklyReview::factory()->create([
            'subject_user_id' => $subject->getKey(),
            'reviewer_id' => $reviewer->getKey(),
        ]);

        $this->assertSame(1, WeeklyReview::query()->visibleTo($subject)->count());
        $this->assertSame(1, WeeklyReview::query()->visibleTo($reviewer)->count());
        $this->assertSame(0, WeeklyReview::query()->visibleTo($peer)->count());
    }

    public function test_improvement_plan_measures_its_duration_and_links_its_actions(): void
    {
        $plan = ImprovementPlan::factory()->lastingDays(7)->create(['start_date' => '2026-08-10']);
        ImprovementPlanAction::factory()->create([
            'improvement_plan_id' => $plan->getKey(),
            'position' => 1,
        ]);

        $this->assertSame(7, $plan->durationInDays());
        $this->assertSame('2026-08-16', $plan->end_date->toDateString());
        $this->assertSame(1, $plan->actions()->count());
        $this->assertSame(7, ImprovementPlan::MINIMUM_DURATION_DAYS);
        $this->assertSame(14, ImprovementPlan::MAXIMUM_DURATION_DAYS);
    }

    public function test_intake_form_requires_three_outcomes_and_records_a_single_decision(): void
    {
        $form = InternshipIntakeForm::factory()->approved()->create();

        $this->assertSame(3, InternshipIntakeForm::REQUIRED_OUTCOMES);
        $this->assertSame(3, $form->outcomes()->count());
        $this->assertSame(InternshipIntakeState::Approuvee, $form->state);
        $this->assertTrue($form->isApproved());
        $this->assertNotNull($form->decided_by);
        $this->assertNotNull($form->decided_at);
    }

    public function test_only_active_internships_occupy_a_tutor_slot(): void
    {
        $tutor = User::factory()->create();
        Internship::factory()->count(2)->forTutor($tutor)->create();
        Internship::factory()->forTutor($tutor)->ended()->create();
        Internship::factory()->forTutor($tutor)->archived()->create();

        $this->assertSame(4, Internship::query()->where('tutor_id', $tutor->getKey())->count());
        $this->assertSame(2, Internship::query()
            ->where('tutor_id', $tutor->getKey())
            ->occupyingTutorSlot()
            ->count());
        $this->assertSame(['actif'], InternshipState::occupyingValues());
    }

    public function test_an_account_carries_at_most_one_internship(): void
    {
        $intern = User::factory()->create();
        Internship::factory()->create(['user_id' => $intern->getKey()]);

        $this->expectException(QueryException::class);

        Internship::factory()->create(['user_id' => $intern->getKey()]);
    }

    public function test_internship_dossier_is_visible_to_intern_tutor_and_direction_only(): void
    {
        $intern = User::factory()->create();
        $tutor = User::factory()->create();
        $peer = User::factory()->create();
        Internship::factory()->create([
            'user_id' => $intern->getKey(),
            'tutor_id' => $tutor->getKey(),
        ]);

        $this->assertSame(1, Internship::query()->visibleTo($intern)->count());
        $this->assertSame(1, Internship::query()->visibleTo($tutor)->count());
        $this->assertSame(0, Internship::query()->visibleTo($peer)->count());
    }

    public function test_internship_carries_plan_evaluations_and_both_checklists(): void
    {
        $internship = Internship::factory()->create();
        InternshipPlan::factory()->create(['internship_id' => $internship->getKey()]);
        InternshipEvaluation::factory()->create(['internship_id' => $internship->getKey()]);
        $final = InternshipEvaluation::factory()->finale()->validated()->create([
            'internship_id' => $internship->getKey(),
        ]);
        InternshipChecklistItem::factory()->create(['internship_id' => $internship->getKey()]);
        InternshipChecklistItem::factory()->exit()->create(['internship_id' => $internship->getKey()]);

        $this->assertNotNull($internship->plan()->first());
        $this->assertSame(2, $internship->evaluations()->count());
        $this->assertSame(2, $internship->checklistItems()->count());
        $this->assertSame(InternshipEvaluationType::Finale, $final->type);
        $this->assertTrue($final->certificate_conditions_met);
        $this->assertFalse($final->isEditable());
        $this->assertCount(6, InternshipChecklistType::Integration->items());
        $this->assertCount(5, InternshipChecklistType::Sortie->items());
    }

    public function test_a_tutor_declares_distinct_support_slots(): void
    {
        $tutor = User::factory()->create();
        TutorSupportSlot::factory()->on(2, '10:00:00')->create(['tutor_id' => $tutor->getKey()]);
        TutorSupportSlot::factory()->on(4, '15:30:00')->create(['tutor_id' => $tutor->getKey()]);

        $this->assertSame(2, TutorSupportSlot::query()->forTutor($tutor)->count());

        $this->expectException(QueryException::class);

        TutorSupportSlot::factory()->on(2, '10:00:00')->create(['tutor_id' => $tutor->getKey()]);
    }

    public function test_a_request_belongs_to_a_single_batch_so_none_is_lost_or_merged(): void
    {
        $tutor = User::factory()->create();
        $batch = SupportRequestBatch::factory()->create(['tutor_id' => $tutor->getKey()]);
        $blockers = Blocker::factory()->count(5)->create(['solicited_user_id' => $tutor->getKey()]);

        foreach ($blockers as $blocker) {
            SupportRequestBatchItem::factory()->create([
                'support_request_batch_id' => $batch->getKey(),
                'blocker_id' => $blocker->getKey(),
            ]);
        }

        // Cinq demandes, cinq objets distincts conservés (AC 38).
        $this->assertSame(5, $batch->items()->count());
        $this->assertSame(5, $batch->items()->distinct()->pluck('blocker_id')->count());
        $this->assertFalse($batch->isDelivered());

        $other = SupportRequestBatch::factory()->create([
            'tutor_id' => $tutor->getKey(),
            'scheduled_for' => $batch->scheduled_for->addWeek(),
        ]);

        $this->expectException(QueryException::class);

        SupportRequestBatchItem::factory()->create([
            'support_request_batch_id' => $other->getKey(),
            'blocker_id' => $blockers->first()->getKey(),
        ]);
    }

    public function test_no_epic_seven_record_can_be_physically_deleted(): void
    {
        $records = [
            WeeklyReview::factory()->create(),
            ImprovementPlan::factory()->create(),
            InternshipIntakeForm::factory()->create(),
            Internship::factory()->create(),
            InternshipEvaluation::factory()->create(),
            TutorSupportSlot::factory()->create(),
        ];

        foreach ($records as $record) {
            try {
                $record->delete();
                $this->fail($record::class.' ne doit pas accepter la suppression physique.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('suppression physique', $exception->getMessage());
            }
        }
    }
}
