<?php

namespace App\Services\Accountability;

use App\Enums\RelationType;
use App\Enums\TaskRequestState;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\TaskRequest;
use App\Models\Identity\User;
use App\Notifications\GenericLinkedNotification;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class TaskRequestService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(DailyReport $report, User $actor, string $description, bool $isUrgent): TaskRequest
    {
        if ($report->author_id !== $actor->getKey()) {
            throw ValidationException::withMessages(['report' => 'Vous ne pouvez demander une tâche que depuis votre rapport.']);
        }
        $responsible = $actor->manager;
        if (! $responsible instanceof User) {
            throw ValidationException::withMessages(['responsible' => "Aucun responsable direct n'est configuré pour votre compte."]);
        }

        $request = DB::connection($report->getConnectionName())->transaction(function () use ($report, $actor, $responsible, $description, $isUrgent): TaskRequest {
            $taskRequest = new TaskRequest;
            $taskRequest->fill([
                'daily_report_id' => $report->getKey(),
                'requested_by' => $actor->getKey(),
                'responsible_id' => $responsible->getKey(),
                'description' => $description,
                'is_urgent' => $isUrgent,
                'state' => TaskRequestState::Ouvert,
            ]);
            $taskRequest->saveOrFail();
            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
                auditable: $taskRequest,
                action: 'task_request_created',
                newValues: ['daily_report_id' => $report->getKey(), 'is_urgent' => $isUrgent, 'state' => TaskRequestState::Ouvert->value],
            );

            return $taskRequest;
        });

        $responsible->notify(new GenericLinkedNotification(
            'Une nouvelle tâche est demandée depuis un rapport quotidien.',
            route('daily-report-reviews.show', $report, false),
        ));

        return $request;
    }

    public function process(TaskRequest $taskRequest, User $actor): TaskRequest
    {
        Gate::forUser($actor)->authorize('process', $taskRequest);

        return DB::connection($taskRequest->getConnectionName())->transaction(function () use ($taskRequest, $actor): TaskRequest {
            $locked = TaskRequest::query()->whereKey($taskRequest->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state !== TaskRequestState::Ouvert) {
                throw ValidationException::withMessages(['request' => 'Cette demande est déjà traitée.']);
            }
            $locked->fill(['state' => TaskRequestState::Traite, 'treated_by' => $actor->getKey(), 'treated_at' => CarbonImmutable::now('UTC')]);
            $locked->saveOrFail();
            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
                auditable: $locked,
                action: 'task_request_processed',
                oldValues: ['state' => TaskRequestState::Ouvert->value],
                newValues: ['state' => TaskRequestState::Traite->value],
            );

            return $locked;
        });
    }

    /**
     * Contrat consommable par la future règle de regroupement des créneaux de suivi 7.6.
     *
     * @return Collection<int, TaskRequest>
     */
    public function pendingNonUrgentInternRequestsFor(User $tutor): Collection
    {
        return TaskRequest::query()
            ->where('responsible_id', $tutor->getKey())
            ->where('state', TaskRequestState::Ouvert)
            ->where('is_urgent', false)
            ->whereHas('requester', fn (Builder $query): Builder => $query->where('relation_type', RelationType::Stagiaire))
            ->with(['requester.person', 'report'])
            ->oldest()
            ->get();
    }
}
