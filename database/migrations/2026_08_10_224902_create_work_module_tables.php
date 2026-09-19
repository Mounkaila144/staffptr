<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_priorities', function (Blueprint $table): void {
            $table->id();
            $table->date('month')->index();
            $table->string('title', 160);
            $table->string('description', 500);
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('indicator', 160);
            $table->string('target', 160);
            $table->date('due_date');
            $table->string('priority', 20);
            $table->string('state', 20)->default('validee')->index();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['month', 'state']);
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('client_name', 160)->nullable();
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 30)->default('prevu')->index();
            $table->unsignedBigInteger('planned_budget_xof')->nullable();
            $table->unsignedBigInteger('spent_budget_xof')->nullable();
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'user_id', 'left_on']);
        });

        Schema::create('project_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('changed_at');
            $table->timestamps();
        });

        Schema::create('objectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('company_priority_id')->nullable()->constrained('company_priorities')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->string('title', 160);
            $table->string('description', 500);
            $table->string('indicator', 160);
            $table->string('target_value', 160);
            $table->string('expected_evidence', 500);
            $table->text('required_means')->nullable();
            $table->date('due_date')->index();
            $table->string('priority', 20);
            $table->string('state', 30)->default('brouillon')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('version_number')->default(1);
            $table->timestamps();
            $table->index(['user_id', 'due_date', 'state']);
        });

        Schema::create('objective_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('objective_id')->constrained('objectives')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('previous_values');
            $table->json('new_values');
            $table->text('reason');
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['objective_id', 'version_number']);
        });

        Schema::create('work_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignId('objective_id')->nullable()->constrained('objectives')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('work_tasks')->restrictOnDelete();
            $table->foreignId('assignee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->date('due_date')->index();
            $table->string('priority', 20);
            $table->string('status', 20)->default('a_faire')->index();
            $table->timestamps();
        });

        Schema::create('work_comments', function (Blueprint $table): void {
            $table->id();
            $table->morphs('commentable');
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->boolean('correction_requested')->default(false);
            $table->timestamps();
        });

        Schema::create('work_links', function (Blueprint $table): void {
            $table->id();
            $table->morphs('linkable');
            $table->string('label', 120);
            $table->string('url', 2048);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('deliverables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->date('planned_date');
            $table->date('actual_date')->nullable();
            $table->string('status', 30)->default('prevu')->index();
            $table->timestamps();
        });

        Schema::create('deliverable_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deliverable_id')->constrained('deliverables')->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliverable_status_histories');
        Schema::dropIfExists('deliverables');
        Schema::dropIfExists('work_links');
        Schema::dropIfExists('work_comments');
        Schema::dropIfExists('work_tasks');
        Schema::dropIfExists('objective_versions');
        Schema::dropIfExists('objectives');
        Schema::dropIfExists('project_status_histories');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('company_priorities');
    }
};
