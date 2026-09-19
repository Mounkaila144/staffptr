<?php

namespace App\Services\Accountability;

use App\Enums\BlockerState;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Task;
use App\Notifications\BlockerNotification;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class BlockerService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AccountabilityDashboardService $dashboardService,
        private readonly SupportRequestGroupingService $grouping,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Blocker
    {
        Gate::forUser($actor)->authorize('create', Blocker::class);
        $origin = $this->origin((string) $data['origin_type'], (int) $data['origin_id']);
        Gate::forUser($actor)->authorize('view', $origin);
        $solicited = User::query()->findOrFail((int) $data['solicited_user_id']);

        $blocker = DB::connection($origin->getConnectionName())->transaction(function () use ($data, $actor, $origin, $solicited): Blocker {
            $blocker = new Blocker;
            $blocker->fill([
                'origin_type' => $origin->getMorphClass(), 'origin_id' => $origin->getKey(),
                'created_by' => $actor->getKey(), 'solicited_user_id' => $solicited->getKey(),
                'problem' => $data['problem'], 'urgency' => $data['urgency'], 'reported_on' => $data['reported_on'],
                'deadline_impact' => $data['deadline_impact'], 'attempted_action' => $data['attempted_action'], 'state' => BlockerState::Ouvert,
            ]);
            $blocker->saveOrFail();
            $this->auditLogger->record(
                actorId: $actor->getKey(), actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
                auditable: $blocker, action: 'blocker_created',
                newValues: ['origin_type' => $origin->getMorphClass(), 'origin_id' => $origin->getKey(), 'urgency' => $blocker->urgency->value, 'state' => BlockerState::Ouvert->value],
            );

            return $blocker;
        });

        $this->dashboardService->invalidateBlockers($actor);
        $this->dashboardService->invalidateBlockers($solicited);

        // Une demande non urgente d'un stagiaire vers un tuteur ayant un créneau est accumulée et
        // présentée au créneau suivant (AC 35). Tout le reste — et notamment tout blocage urgent —
        // conserve la notification immédiate (AC 36, 39).
        if ($this->grouping->enqueue($blocker) === null) {
            Notification::sendNow($solicited, BlockerNotification::forDatabase($blocker), ['database']);
            $solicited->notify(BlockerNotification::forWhatsApp($blocker));
        }

        return $blocker;
    }

    public function transition(Blocker $blocker, User $actor, BlockerState $target, ?string $reason = null): Blocker
    {
        Gate::forUser($actor)->authorize('transition', $blocker);
        if ($target === BlockerState::FermeSansSolution && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['closure_reason' => 'Indiquez pourquoi le blocage est fermé sans solution.']);
        }

        $updated = DB::connection($blocker->getConnectionName())->transaction(function () use ($blocker, $actor, $target, $reason): Blocker {
            $locked = Blocker::query()->whereKey($blocker->getKey())->lockForUpdate()->firstOrFail();
            $allowed = match ($locked->state) {
                BlockerState::Ouvert => [BlockerState::PrisEnCharge, BlockerState::FermeSansSolution],
                BlockerState::PrisEnCharge => [BlockerState::Resolu, BlockerState::FermeSansSolution],
                default => [],
            };
            if (! in_array($target, $allowed, true)) {
                throw ValidationException::withMessages(['state' => "Cette transition de blocage n'est pas autorisée."]);
            }
            $old = $locked->state;
            $locked->state = $target;
            if ($target === BlockerState::PrisEnCharge) {
                $locked->acknowledged_at = CarbonImmutable::now('UTC');
            }
            if ($target === BlockerState::Resolu) {
                $locked->resolved_at = CarbonImmutable::now('UTC');
            }
            if ($target === BlockerState::FermeSansSolution) {
                $locked->closure_reason = $reason;
            }
            $locked->saveOrFail();
            $this->auditLogger->record(
                actorId: $actor->getKey(), actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
                auditable: $locked, action: 'blocker_transitioned', oldValues: ['state' => $old->value], newValues: ['state' => $target->value], reason: $reason,
            );

            return $locked->load(['creator', 'solicitedUser']);
        });

        $this->dashboardService->invalidateBlockers($updated->creator);
        $this->dashboardService->invalidateBlockers($updated->solicitedUser);

        return $updated;
    }

    private function origin(string $type, int $id): Model
    {
        $class = match ($type) {
            'task' => Task::class, 'objective' => Objective::class, 'daily_report' => DailyReport::class, default => null
        };
        if ($class === null) {
            throw ValidationException::withMessages(['origin_type' => "Cette origine de blocage n'est pas reconnue."]);
        }

        return $class::query()->findOrFail($id);
    }
}
