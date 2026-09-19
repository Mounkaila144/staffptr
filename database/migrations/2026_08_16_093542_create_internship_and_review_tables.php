<?php

use App\Enums\ImprovementPlanState;
use App\Enums\InternshipIntakeState;
use App\Enums\InternshipState;
use App\Enums\WeeklyReviewState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Revue hebdomadaire (AC 1 à 8) ---------------------------------------------------

        Schema::create('weekly_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->date('week_start_date');
            $table->date('scheduled_on');
            $table->string('state', 30)->default(WeeklyReviewState::Brouillon->value)->index();
            $table->text('reviewee_comment')->nullable();
            $table->text('reviewer_comment')->nullable();
            $table->foreignId('reviewee_validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewee_validated_at', 3)->nullable();
            $table->foreignId('reviewer_validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewer_validated_at', 3)->nullable();
            $table->timestamps(3);
            $table->unique(['subject_user_id', 'week_start_date']);
            $table->index(['reviewer_id', 'week_start_date']);
        });

        Schema::create('weekly_review_objectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('weekly_review_id')->constrained('weekly_reviews')->restrictOnDelete();
            $table->foreignId('objective_id')->constrained('objectives')->restrictOnDelete();
            $table->text('result');
            $table->text('evidence')->nullable();
            $table->string('status', 30);
            $table->text('gap_cause')->nullable();
            $table->text('next_action');
            $table->timestamps(3);
            $table->unique(['weekly_review_id', 'objective_id']);
        });

        // --- Plan d'amélioration (AC 9 à 13) -------------------------------------------------

        Schema::create('improvement_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('weekly_review_id')->constrained('weekly_reviews')->restrictOnDelete();
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('support_provided');
            $table->text('observed_result')->nullable();
            $table->string('state', 20)->default(ImprovementPlanState::EnCours->value)->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at', 3)->nullable();
            $table->timestamps(3);
            $table->index(['subject_user_id', 'state']);
        });

        Schema::create('improvement_plan_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('improvement_plan_id')->constrained('improvement_plans')->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->text('description');
            $table->date('due_date');
            $table->timestamp('completed_at', 3)->nullable();
            $table->timestamps(3);
            $table->unique(['improvement_plan_id', 'position']);
        });

        // --- Fiche d'entrée du stagiaire (AC 14 à 16) ----------------------------------------

        Schema::create('internship_intake_forms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            // Le tuteur est exigé à la soumission, mais la colonne reste nullable : un tuteur
            // peut quitter l'entreprise entre l'approbation et l'activation. La désignation d'un
            // tuteur est ainsi vérifiée séparément de l'approbation de la fiche (AC 16, 41).
            $table->foreignId('tutor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('real_need');
            $table->text('mission');
            $table->unsignedSmallInteger('duration_weeks');
            $table->text('tools');
            $table->string('state', 20)->default(InternshipIntakeState::Brouillon->value)->index();
            $table->timestamp('submitted_at', 3)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at', 3)->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps(3);
            $table->index(['candidate_user_id', 'state']);
        });

        Schema::create('internship_intake_outcomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('internship_intake_form_id')->constrained('internship_intake_forms')->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->text('description');
            $table->timestamps(3);
            $table->unique(['internship_intake_form_id', 'position'], 'intake_outcome_position_unique');
        });

        // --- Stage, plan, évaluations et checklists (AC 17, 27 à 32) -------------------------

        Schema::create('internships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('internship_intake_form_id')->constrained('internship_intake_forms')->restrictOnDelete();
            $table->foreignId('tutor_id')->constrained('users')->restrictOnDelete();
            $table->string('state', 20)->default(InternshipState::Actif->value);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamp('ended_at', 3)->nullable();
            $table->timestamps(3);
            // Index de comptage de la charge d'un tuteur (AC 20 à 24).
            $table->index(['tutor_id', 'state']);
        });

        Schema::create('internship_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('internship_id')->unique()->constrained('internships')->restrictOnDelete();
            $table->text('skills_to_learn');
            $table->text('objectives');
            $table->text('weekly_tasks');
            $table->text('expected_evidence');
            $table->timestamps(3);
        });

        Schema::create('internship_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->restrictOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 20);
            $table->date('week_start_date')->nullable();
            $table->text('observed_progress');
            $table->text('evidence')->nullable();
            $table->text('next_steps')->nullable();
            $table->boolean('certificate_conditions_met')->nullable();
            $table->timestamp('validated_at', 3)->nullable();
            $table->timestamps(3);
            $table->index(['internship_id', 'type']);
            $table->unique(['internship_id', 'type', 'week_start_date']);
        });

        Schema::create('internship_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->restrictOnDelete();
            $table->string('checklist_type', 20);
            $table->unsignedTinyInteger('position');
            $table->string('label', 150);
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at', 3)->nullable();
            $table->timestamps(3);
            $table->unique(['internship_id', 'checklist_type', 'position'], 'internship_checklist_position_unique');
        });

        // --- Créneaux de suivi et regroupement des demandes (AC 34 à 39) ---------------------

        Schema::create('tutor_support_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_id')->constrained('users')->restrictOnDelete();
            // 1 = lundi … 7 = dimanche, jours civils de Niamey.
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->timestamps(3);
            $table->unique(['tutor_id', 'weekday', 'start_time']);
        });

        Schema::create('support_request_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('scheduled_for', 3);
            $table->timestamp('delivered_at', 3)->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->timestamps(3);
            $table->unique(['tutor_id', 'scheduled_for']);
        });

        Schema::create('support_request_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_request_batch_id')->constrained('support_request_batches')->restrictOnDelete();
            $table->foreignId('blocker_id')->constrained('blockers')->restrictOnDelete();
            $table->timestamps(3);
            // Une demande n'appartient qu'à un seul envoi : ni perte, ni doublon, ni fusion (AC 38).
            $table->unique('blocker_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_request_batch_items');
        Schema::dropIfExists('support_request_batches');
        Schema::dropIfExists('tutor_support_slots');
        Schema::dropIfExists('internship_checklist_items');
        Schema::dropIfExists('internship_evaluations');
        Schema::dropIfExists('internship_plans');
        Schema::dropIfExists('internships');
        Schema::dropIfExists('internship_intake_outcomes');
        Schema::dropIfExists('internship_intake_forms');
        Schema::dropIfExists('improvement_plan_actions');
        Schema::dropIfExists('improvement_plans');
        Schema::dropIfExists('weekly_review_objectives');
        Schema::dropIfExists('weekly_reviews');
    }
};
