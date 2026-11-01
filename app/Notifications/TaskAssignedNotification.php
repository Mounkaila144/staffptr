<?php

namespace App\Notifications;

use App\Models\Identity\User;
use App\Models\Work\Task;
use App\Services\Platform\WhatsAppChannel;

/**
 * Prévient la personne à qui une tâche vient d'être confiée (décision du propriétaire du
 * 2026-11-01). Jamais envoyée quand on se confie la tâche à soi-même : le service filtre ce cas
 * avant l'envoi, et `shouldSend()` le refuse encore au moment de la livraison.
 */
class TaskAssignedNotification extends BaseNotification
{
    /** @param list<string> $channels */
    public function __construct(
        public readonly int $taskId,
        public readonly int $assigneeId,
        public readonly int $assignerId,
        public readonly string $assignerName,
        public readonly string $title,
        public readonly string $dueDate,
        private readonly array $channels,
    ) {}

    public static function forDatabase(Task $task, User $assigner): self
    {
        return self::make($task, $assigner, ['database']);
    }

    public static function forWhatsApp(Task $task, User $assigner): self
    {
        return self::make($task, $assigner, [WhatsAppChannel::class]);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, task_id: int} */
    public function toDatabase(object $notifiable): array
    {
        return ['message' => $this->message(), 'link' => $this->link(), 'task_id' => $this->taskId];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".url($this->link());
    }

    /**
     * La tâche a pu être confiée à quelqu'un d'autre entre la mise en file et l'envoi : seul son
     * responsable actuel est prévenu, et jamais l'auteur de l'action lui-même.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User
            || (int) $notifiable->getKey() !== $this->assigneeId
            || $this->assigneeId === $this->assignerId) {
            return false;
        }

        return Task::query()->whereKey($this->taskId)->where('assignee_id', $this->assigneeId)->exists();
    }

    /** @param list<string> $channels */
    private static function make(Task $task, User $assigner, array $channels): self
    {
        return new self(
            (int) $task->getKey(),
            (int) $task->assignee_id,
            (int) $assigner->getKey(),
            $assigner->person()->value('full_name') ?? "Compte #{$assigner->getKey()}",
            $task->title,
            $task->due_date->format('d/m/Y'),
            $channels,
        );
    }

    private function link(): string
    {
        return route('tasks.show', $this->taskId, absolute: false);
    }

    private function message(): string
    {
        return "{$this->assignerName} vous a confié la tâche « {$this->title} », à terminer pour le {$this->dueDate}.";
    }
}
