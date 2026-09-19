<?php

namespace App\Services\Work;

use App\Enums\WorkTaskStatus;
use App\Models\Identity\User;
use App\Models\Work\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class TodayTaskService
{
    /** @return Collection<int, Task> */
    public function forUser(User $user, ?CarbonImmutable $day = null): Collection
    {
        $day ??= CarbonImmutable::now('Africa/Niamey');

        return Task::query()->select(['id', 'project_id', 'objective_id', 'parent_id', 'assignee_id', 'title', 'due_date', 'priority', 'status'])
            ->with(['project:id,name', 'objective:id,title'])->where('assignee_id', $user->getKey())
            ->whereDate('due_date', $day->toDateString())->whereNotIn('status', [WorkTaskStatus::Terminee, WorkTaskStatus::Annulee])
            ->orderByDesc('priority')->orderBy('id')->get();
    }
}
