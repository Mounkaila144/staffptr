<?php

namespace Tests\Feature\Http;

use App\Enums\WorkTaskStatus;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Models\Work\Task;
use Database\Seeders\SettingSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

class TaskStatusHttpTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    #[Test]
    public function the_assignee_moves_a_task_forward_and_the_change_is_audited(): void
    {
        $employee = User::factory()->active()->withRole('employe')->create();
        $task = Task::factory()->for($employee, 'assignee')->create(['created_by' => $employee->getKey(), 'status' => WorkTaskStatus::AFaire]);

        $this->actingAs($employee)->patch("/taches/{$task->getKey()}/etat", ['status' => 'en_cours'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($employee)->patch("/taches/{$task->getKey()}/etat", ['status' => 'terminee', 'reason' => 'Maquette livrée'])->assertSessionHasNoErrors();

        $this->assertSame(WorkTaskStatus::Terminee, $task->refresh()->status);
        $audit = AuditLog::query()->where('action', 'work_task_status_changed')->latest('id')->firstOrFail();
        $this->assertSame('Maquette livrée', $audit->reason);
        $this->assertSame(['En cours → Terminée : Maquette livrée'], $task->comments()->pluck('body')->all());
    }

    #[Test]
    public function a_forbidden_transition_is_refused_with_a_readable_message(): void
    {
        $employee = User::factory()->active()->withRole('employe')->create();
        $task = Task::factory()->for($employee, 'assignee')->create(['created_by' => $employee->getKey(), 'status' => WorkTaskStatus::Terminee]);

        $this->actingAs($employee)->patch("/taches/{$task->getKey()}/etat", ['status' => 'annulee'])
            ->assertSessionHasErrors(['status' => 'Le passage de Terminée vers Annulée n’est pas autorisé.']);
        $this->assertSame(WorkTaskStatus::Terminee, $task->refresh()->status);
    }

    #[Test]
    public function someone_outside_the_task_cannot_change_its_status(): void
    {
        $assignee = User::factory()->active()->withRole('employe')->create();
        $outsider = User::factory()->active()->withRole('employe')->create();
        $task = Task::factory()->for($assignee, 'assignee')->create(['created_by' => $assignee->getKey(), 'status' => WorkTaskStatus::AFaire]);

        $this->actingAs($outsider)->patch("/taches/{$task->getKey()}/etat", ['status' => 'terminee'])->assertForbidden();
        $this->assertSame(WorkTaskStatus::AFaire, $task->refresh()->status);
    }

    #[Test]
    public function the_task_page_offers_only_the_allowed_transitions(): void
    {
        $employee = User::factory()->active()->withRole('employe')->create();
        $task = Task::factory()->for($employee, 'assignee')->create(['created_by' => $employee->getKey(), 'status' => WorkTaskStatus::Terminee]);

        $this->actingAs($employee)->get("/taches/{$task->getKey()}")->assertInertia(fn (Assert $page): Assert => $page
            ->component('Work/Tasks/Show')
            ->where('canUpdate', true)
            ->where('task.transitions', [['value' => 'en_cours', 'label' => 'En cours']]));
    }
}
