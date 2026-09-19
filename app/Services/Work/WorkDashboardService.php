<?php

namespace App\Services\Work;

use App\Models\Identity\User;
use App\Models\Work\Objective;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class WorkDashboardService
{
    public function __construct(private readonly TodayTaskService $todayTaskService) {}

    /** @return array<string, mixed> */
    public function blocks(User $user): array
    {
        $month = CarbonImmutable::now('Africa/Niamey')->startOfMonth();

        return [
            'objectives' => $user->can('objectif_individuel.consulter') ? Cache::remember($this->key($user, 'objectives'), now()->addMinutes(5), fn (): array => $this->objectives($user, $month)) : null,
            'todayTasks' => $user->can('tache.consulter') ? Cache::remember($this->key($user, 'tasks'), now()->addMinutes(5), fn (): array => $this->tasks($user)) : null,
            'deadlines' => $user->can('objectif_individuel.consulter') ? Cache::remember($this->key($user, 'deadlines'), now()->addMinutes(5), fn (): array => $this->deadlines($user)) : null,
            'notifications' => $user->can('tableau_bord.consulter') ? ['count' => $user->unreadNotifications()->count(), 'url' => route('notifications.index')] : null,
        ];
    }

    public function invalidate(User $user): void
    {
        foreach (['objectives', 'tasks', 'deadlines'] as $block) {
            Cache::forget($this->key($user, $block));
        }
    }

    /** @return array<string, mixed> */
    private function objectives(User $user, CarbonImmutable $month): array
    {
        $items = Objective::query()->select(['id', 'user_id', 'title', 'state', 'progress', 'due_date'])->visibleTo($user)->where('user_id', $user->getKey())->dueInMonth($month)->orderBy('due_date')->limit(5)->get();

        return ['title' => 'Mes objectifs du mois', 'empty_message' => 'Aucun objectif validé pour ce mois.', 'url' => route('objectives.index'), 'items' => $items->map(fn (Objective $objective): array => ['id' => $objective->getKey(), 'title' => $objective->title, 'state' => $objective->state->value, 'state_label' => $objective->state->label(), 'tone' => $objective->state->tone(), 'progress' => $objective->progress])->all()];
    }

    /** @return array<string, mixed> */
    private function tasks(User $user): array
    {
        $items = $this->todayTaskService->forUser($user);

        return ['title' => 'Mes tâches du jour', 'empty_message' => 'Aucune tâche prévue aujourd’hui.', 'url' => route('tasks.today'), 'items' => $items->map(fn ($task): array => ['id' => $task->getKey(), 'title' => $task->title, 'status_label' => $task->status->label(), 'priority_label' => $task->priority->label()])->all()];
    }

    /** @return array<string, mixed> */
    private function deadlines(User $user): array
    {
        $until = CarbonImmutable::now('Africa/Niamey')->addDays(14)->toDateString();
        $objectives = Objective::query()->select(['id', 'user_id', 'title', 'due_date'])->visibleTo($user)->whereBetween('due_date', [now('Africa/Niamey')->toDateString(), $until])->orderBy('due_date')->limit(5)->get();

        return ['title' => 'Prochaines échéances', 'empty_message' => 'Aucune échéance dans les 14 prochains jours.', 'items' => $objectives->map(fn (Objective $objective): array => ['title' => $objective->title, 'date' => $objective->due_date->format('d/m/Y')])->all()];
    }

    private function key(User $user, string $block): string
    {
        return "dashboard:user:{$user->getKey()}:{$block}";
    }
}
