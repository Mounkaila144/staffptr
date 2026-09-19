<?php

use App\Enums\BlockerState;
use App\Enums\BlockerUrgency;
use App\Enums\DailyReportDecisionType;
use App\Enums\DailyReportState;
use App\Enums\TaskRequestState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->date('report_date');
            $table->string('state', 20)->default(DailyReportState::Brouillon->value)->index();
            $table->timestamp('submitted_at', 3)->nullable();
            $table->text('lateness_explanation')->nullable();
            $table->timestamps(3);
            $table->unique(['author_id', 'report_date']);
            $table->index(['report_date', 'state']);
        });

        Schema::create('daily_report_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->text('planned_task');
            $table->text('achieved_result');
            $table->string('evidence_link', 2048)->nullable();
            $table->boolean('blocker_present')->default(false);
            $table->text('blocker_details')->nullable();
            $table->text('next_action');
            $table->boolean('help_requested')->default(false);
            $table->text('help_details')->nullable();
            $table->text('correction_reason')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(['daily_report_id', 'version_number']);
        });

        Schema::create('daily_report_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps(3);
        });

        Schema::create('daily_report_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20)->default(DailyReportDecisionType::Valider->value);
            $table->text('reason')->nullable();
            $table->timestamp('decided_at', 3);
            $table->timestamps(3);
            $table->index(['daily_report_id', 'decided_at']);
        });

        Schema::create('task_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('responsible_id')->constrained('users')->restrictOnDelete();
            $table->text('description');
            $table->boolean('is_urgent')->default(false);
            $table->string('state', 20)->default(TaskRequestState::Ouvert->value)->index();
            $table->foreignId('treated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('treated_at', 3)->nullable();
            $table->timestamps(3);
        });

        Schema::create('blockers', function (Blueprint $table): void {
            $table->id();
            $table->morphs('origin');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('solicited_user_id')->constrained('users')->restrictOnDelete();
            $table->text('problem');
            $table->string('urgency', 20)->default(BlockerUrgency::Normale->value);
            $table->date('reported_on');
            $table->text('deadline_impact');
            $table->text('attempted_action');
            $table->string('state', 30)->default(BlockerState::Ouvert->value)->index();
            $table->timestamp('acknowledged_at', 3)->nullable();
            $table->timestamp('resolved_at', 3)->nullable();
            $table->text('closure_reason')->nullable();
            $table->timestamps(3);
            $table->index(['solicited_user_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockers');
        Schema::dropIfExists('task_requests');
        Schema::dropIfExists('daily_report_decisions');
        Schema::dropIfExists('daily_report_comments');
        Schema::dropIfExists('daily_report_versions');
        Schema::dropIfExists('daily_reports');
    }
};
