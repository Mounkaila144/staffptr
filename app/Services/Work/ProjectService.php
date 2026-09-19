<?php

namespace App\Services\Work;

use App\Enums\ProjectStatus;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Work\Project;
use App\Models\Work\ProjectMember;
use App\Models\Work\ProjectStatusHistory;
use App\Models\Work\WorkComment;
use App\Models\Work\WorkLink;
use App\Services\Platform\AttachmentService;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProjectService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AttachmentService $attachmentService,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor, ?Attachment $attachment = null): Project
    {
        $project = new Project;

        return DB::connection($project->getConnectionName())->transaction(function () use ($project, $data, $actor, $attachment): Project {
            if ($attachment instanceof Attachment) {
                $this->assertStagedForActor($attachment, $actor);
            }
            $project->fill(array_diff_key($data, array_flip(['attachment_ulid'])));
            $this->auditLogger->runExplicitly($project, fn (): bool => $project->saveOrFail(), $actor->getKey(), $this->actorLabel($actor), 'project_created', newValues: $project->getAttributes());
            ProjectStatusHistory::query()->create(['project_id' => $project->getKey(), 'from_status' => null, 'to_status' => $project->status, 'actor_id' => $actor->getKey(), 'reason' => 'Création du projet.', 'changed_at' => CarbonImmutable::now('UTC')]);
            if ($attachment instanceof Attachment) {
                $this->attachmentService->attachExisting($attachment, $project, $actor);
            }

            return $project->load('manager.person');
        });
    }

    public function transition(Project $project, ProjectStatus $target, string $reason, User $actor): Project
    {
        return DB::connection($project->getConnectionName())->transaction(function () use ($project, $target, $reason, $actor): Project {
            $locked = Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => "Le passage de {$from->label()} vers {$target->label()} n’est pas autorisé."]);
            }
            $locked->status = $target;
            $this->auditLogger->runExplicitly($locked, fn (): bool => $locked->saveOrFail(), $actor->getKey(), $this->actorLabel($actor), 'project_status_changed', ['status' => $from->value], ['status' => $target->value], $reason);
            ProjectStatusHistory::query()->create(['project_id' => $locked->getKey(), 'from_status' => $from, 'to_status' => $target, 'actor_id' => $actor->getKey(), 'reason' => $reason, 'changed_at' => CarbonImmutable::now('UTC')]);

            return $locked->refresh();
        });
    }

    public function addMember(Project $project, User $member, string $joinedOn, User $actor): ProjectMember
    {
        return DB::connection($project->getConnectionName())->transaction(function () use ($project, $member, $joinedOn, $actor): ProjectMember {
            $active = ProjectMember::query()->where('project_id', $project->getKey())->where('user_id', $member->getKey())->whereNull('left_on')->exists();
            if ($active) {
                throw ValidationException::withMessages(['user_id' => 'Cette personne participe déjà au projet.']);
            }
            $membership = ProjectMember::query()->create(['project_id' => $project->getKey(), 'user_id' => $member->getKey(), 'joined_on' => $joinedOn, 'added_by' => $actor->getKey()]);
            $this->auditLogger->record($actor->getKey(), $this->actorLabel($actor), $project, 'project_member_added', newValues: ['user_id' => $member->getKey(), 'joined_on' => $joinedOn]);

            return $membership;
        });
    }

    public function removeMember(Project $project, User $member, string $leftOn, User $actor): ProjectMember
    {
        return DB::connection($project->getConnectionName())->transaction(function () use ($project, $member, $leftOn, $actor): ProjectMember {
            $membership = ProjectMember::query()->where('project_id', $project->getKey())->where('user_id', $member->getKey())->whereNull('left_on')->lockForUpdate()->firstOrFail();
            $membership->fill(['left_on' => $leftOn, 'removed_by' => $actor->getKey()])->saveOrFail();
            $this->auditLogger->record($actor->getKey(), $this->actorLabel($actor), $project, 'project_member_removed', oldValues: ['left_on' => null], newValues: ['user_id' => $member->getKey(), 'left_on' => $leftOn]);

            return $membership->refresh();
        });
    }

    public function comment(Project $project, string $body, User $actor): WorkComment
    {
        return DB::connection($project->getConnectionName())->transaction(function () use ($project, $body, $actor): WorkComment {
            $comment = $project->comments()->create(['author_id' => $actor->getKey(), 'body' => $body]);
            $this->auditLogger->record($actor->getKey(), $this->actorLabel($actor), $project, 'project_commented', newValues: ['comment_id' => $comment->getKey()]);

            return $comment;
        });
    }

    public function addLink(Project $project, string $label, string $url, User $actor): WorkLink
    {
        return DB::connection($project->getConnectionName())->transaction(function () use ($project, $label, $url, $actor): WorkLink {
            $link = $project->links()->create(['label' => $label, 'url' => $url, 'created_by' => $actor->getKey()]);
            $this->auditLogger->record($actor->getKey(), $this->actorLabel($actor), $project, 'project_link_added', newValues: ['link_id' => $link->getKey(), 'url' => $url]);

            return $link;
        });
    }

    public function attach(Project $project, Attachment $attachment, User $actor): Attachment
    {
        $this->assertStagedForActor($attachment, $actor);

        return $this->attachmentService->attachExisting($attachment, $project, $actor);
    }

    private function assertStagedForActor(Attachment $attachment, User $actor): void
    {
        if ($attachment->getAttribute('attachable_type') !== $actor->person->getMorphClass()
            || (int) $attachment->getAttribute('attachable_id') !== (int) $actor->person_id
            || (int) $attachment->getAttribute('uploaded_by') !== (int) $actor->getKey()) {
            throw ValidationException::withMessages(['attachment_ulid' => 'Cette pièce jointe ne peut pas être rattachée à ce projet.']);
        }
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
