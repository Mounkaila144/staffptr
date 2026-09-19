<?php

namespace App\Services\Work;

use App\Enums\ObjectiveState;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Work\Objective;
use App\Models\Work\ObjectiveVersion;
use App\Models\Work\WorkComment;
use App\Services\Platform\AttachmentService;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ObjectiveService
{
    private const VERSIONED_FIELDS = ['title', 'description', 'indicator', 'target_value', 'expected_evidence', 'required_means', 'due_date', 'priority', 'company_priority_id', 'project_id', 'progress'];

    public function __construct(private readonly AuditLogger $auditLogger, private readonly AttachmentService $attachmentService, private readonly WorkDashboardService $dashboard) {}

    /** @param array<string, mixed> $data */
    public function propose(array $data, User $actor, ?Attachment $attachment = null): Objective
    {
        $objective = new Objective;

        return DB::connection($objective->getConnectionName())->transaction(function () use ($objective, $data, $actor, $attachment): Objective {
            if ($attachment instanceof Attachment) {
                $this->assertStagedForActor($attachment, $actor);
            }
            $objective->fill([...$data, 'created_by' => $actor->getKey(), 'state' => ObjectiveState::Brouillon, 'progress' => (int) ($data['progress'] ?? 0)]);
            $this->auditLogger->runExplicitly($objective, fn (): bool => $objective->saveOrFail(), $actor->getKey(), $this->actorLabel($actor), 'objective_proposed', newValues: $objective->getAttributes());
            if ($attachment instanceof Attachment) {
                $this->attachmentService->attachExisting($attachment, $objective, $actor);
            }
            $this->dashboard->invalidate($objective->owner);

            return $objective->load(['owner.person', 'companyPriority', 'project', 'attachments']);
        });
    }

    public function validate(Objective $objective, User $actor): Objective
    {
        return DB::connection($objective->getConnectionName())->transaction(function () use ($objective, $actor): Objective {
            $locked = Objective::query()->whereKey($objective->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state !== ObjectiveState::Brouillon) {
                throw ValidationException::withMessages(['state' => 'Seul un objectif brouillon peut être validé.']);
            }
            User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            $month = CarbonImmutable::parse($locked->due_date)->startOfMonth();
            $count = Objective::query()->where('user_id', $locked->user_id)->dueInMonth($month)->whereIn('state', array_map(static fn (ObjectiveState $state): string => $state->value, array_filter(ObjectiveState::cases(), static fn (ObjectiveState $state): bool => $state->countsTowardMonthlyLimit())))->count();
            if ($count >= 3) {
                $label = $month->locale('fr')->isoFormat('MMMM');
                throw ValidationException::withMessages(['state' => "Vous avez déjà 3 objectifs majeurs validés pour {$label}. Terminez-en un ou reportez-le avant d'en valider un quatrième."]);
            }
            $result = $this->saveTransition($locked, ObjectiveState::Valide, $actor, 'objective_validated');
            $this->dashboard->invalidate($result->owner);

            return $result;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Objective $objective, array $data, ?string $reason, User $actor): Objective
    {
        return DB::connection($objective->getConnectionName())->transaction(function () use ($objective, $data, $reason, $actor): Objective {
            $locked = Objective::query()->whereKey($objective->getKey())->lockForUpdate()->firstOrFail();
            $old = Arr::only($locked->getAttributes(), self::VERSIONED_FIELDS);
            $locked->fill(Arr::only($data, self::VERSIONED_FIELDS));
            $new = Arr::only($locked->getAttributes(), self::VERSIONED_FIELDS);
            if ($locked->state !== ObjectiveState::Brouillon) {
                if (trim((string) $reason) === '') {
                    throw ValidationException::withMessages(['reason' => 'Le motif est obligatoire après validation de l’objectif.']);
                }
                $locked->version_number++;
                ObjectiveVersion::query()->create(['objective_id' => $locked->getKey(), 'version_number' => $locked->version_number, 'previous_values' => $old, 'new_values' => $new, 'reason' => $reason, 'author_id' => $actor->getKey()]);
            }
            $this->auditLogger->runExplicitly($locked, fn (): bool => $locked->saveOrFail(), $actor->getKey(), $this->actorLabel($actor), 'objective_updated', $old, $new, $reason);
            $locked->refresh()->load(['owner.person', 'versions.author.person']);
            $this->dashboard->invalidate($locked->owner);

            return $locked;
        });
    }

    public function transition(Objective $objective, ObjectiveState $target, User $actor, ?Attachment $attachment = null): Objective
    {
        return DB::connection($objective->getConnectionName())->transaction(function () use ($objective, $target, $actor, $attachment): Objective {
            $locked = Objective::query()->with('attachments')->whereKey($objective->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->state->canTransitionTo($target)) {
                throw ValidationException::withMessages(['state' => "La transition de {$locked->state->label()} vers {$target->label()} n’est pas autorisée."]);
            }
            if ($attachment instanceof Attachment) {
                $this->assertStagedForActor($attachment, $actor);
                $this->attachmentService->attachExisting($attachment, $locked, $actor);
                $locked->load('attachments');
            }
            if ($target === ObjectiveState::Atteint && $locked->attachments->isEmpty()) {
                throw ValidationException::withMessages(['attachment_ulid' => "Ajoutez une preuve avant de déclarer cet objectif atteint. Preuve attendue : {$locked->expected_evidence}"]);
            }
            $result = $this->saveTransition($locked, $target, $actor, 'objective_state_changed');
            $this->dashboard->invalidate($result->owner);

            return $result;
        });
    }

    public function copyToNextMonth(Objective $objective, User $actor): Objective
    {
        $copy = Arr::except($objective->getAttributes(), ['id', 'state', 'created_at', 'updated_at', 'version_number']);
        $copy['due_date'] = CarbonImmutable::parse($objective->due_date)->addMonthNoOverflow()->toDateString();

        return $this->propose($copy, $actor);
    }

    public function comment(Objective $objective, string $body, bool $correctionRequested, User $actor): WorkComment
    {
        return DB::connection($objective->getConnectionName())->transaction(function () use ($objective, $body, $correctionRequested, $actor): WorkComment {
            $comment = $objective->comments()->create(['author_id' => $actor->getKey(), 'body' => $body, 'correction_requested' => $correctionRequested]);
            $this->auditLogger->record($actor->getKey(), $this->actorLabel($actor), $objective, $correctionRequested ? 'objective_correction_requested' : 'objective_commented', newValues: ['comment_id' => $comment->getKey(), 'body' => $body]);

            return $comment;
        });
    }

    private function saveTransition(Objective $objective, ObjectiveState $target, User $actor, string $action): Objective
    {
        $old = ['state' => $objective->state->value];
        $objective->state = $target;
        $this->auditLogger->runExplicitly($objective, fn (): bool => $objective->saveOrFail(), $actor->getKey(), $this->actorLabel($actor), $action, $old, ['state' => $target->value]);

        return $objective->refresh();
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }

    private function assertStagedForActor(Attachment $attachment, User $actor): void
    {
        if ($attachment->getAttribute('attachable_type') !== $actor->person->getMorphClass()
            || (int) $attachment->getAttribute('attachable_id') !== (int) $actor->person_id
            || (int) $attachment->getAttribute('uploaded_by') !== (int) $actor->getKey()) {
            throw ValidationException::withMessages(['attachment_ulid' => 'Cette preuve ne peut pas être rattachée à cet objectif.']);
        }
    }
}
