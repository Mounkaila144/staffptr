<?php

namespace App\Services\Work;

use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Work\Task;
use App\Models\Work\WorkComment;
use App\Models\Work\WorkLink;
use App\Services\Platform\AttachmentService;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TaskService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly WorkDashboardService $dashboard,
        private readonly AttachmentService $attachmentService,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor, ?Attachment $attachment = null): Task
    {
        $task = new Task;

        return DB::connection($task->getConnectionName())->transaction(function () use ($task, $data, $actor, $attachment): Task {
            if ($attachment instanceof Attachment) {
                $this->assertStagedForActor($attachment, $actor);
            }
            if (isset($data['parent_id'])) {
                $parent = Task::query()->whereKey($data['parent_id'])->lockForUpdate()->firstOrFail();
                if ($parent->parent_id !== null) {
                    throw ValidationException::withMessages(['parent_id' => 'Une sous-tâche ne peut pas contenir une autre sous-tâche.']);
                }
            }
            $task->fill([...array_diff_key($data, array_flip(['attachment_ulid', 'link_label', 'link_url'])), 'created_by' => $actor->getKey()]);
            $this->auditLogger->runExplicitly($task, fn (): bool => $task->saveOrFail(), $actor->getKey(), $this->actorLabel($actor), 'work_task_created', newValues: $task->getAttributes());
            if ($attachment instanceof Attachment) {
                $this->attachmentService->attachExisting($attachment, $task, $actor);
            }
            $task->load(['assignee.person', 'project', 'objective']);
            $this->dashboard->invalidate($task->assignee);

            return $task;
        });
    }

    public function comment(Task $task, string $body, User $actor): WorkComment
    {
        return DB::connection($task->getConnectionName())->transaction(function () use ($task, $body, $actor): WorkComment {
            $comment = $task->comments()->create(['author_id' => $actor->getKey(), 'body' => $body]);
            $this->auditLogger->record($actor->getKey(), $this->actorLabel($actor), $task, 'work_task_commented', newValues: ['comment_id' => $comment->getKey()]);

            return $comment;
        });
    }

    public function addLink(Task $task, string $label, string $url, User $actor): WorkLink
    {
        return DB::connection($task->getConnectionName())->transaction(function () use ($task, $label, $url, $actor): WorkLink {
            $link = $task->links()->create(['label' => $label, 'url' => $url, 'created_by' => $actor->getKey()]);
            $this->auditLogger->record($actor->getKey(), $this->actorLabel($actor), $task, 'work_task_link_added', newValues: ['link_id' => $link->getKey(), 'url' => $url]);

            return $link;
        });
    }

    public function attach(Task $task, Attachment $attachment, User $actor): Attachment
    {
        $this->assertStagedForActor($attachment, $actor);

        return $this->attachmentService->attachExisting($attachment, $task, $actor);
    }

    private function assertStagedForActor(Attachment $attachment, User $actor): void
    {
        if ($attachment->getAttribute('attachable_type') !== $actor->person->getMorphClass()
            || (int) $attachment->getAttribute('attachable_id') !== (int) $actor->person_id
            || (int) $attachment->getAttribute('uploaded_by') !== (int) $actor->getKey()) {
            throw ValidationException::withMessages(['attachment_ulid' => 'Cette pièce jointe ne peut pas être rattachée à cette tâche.']);
        }
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
