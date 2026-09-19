<?php

namespace App\Models\Work;

use App\Enums\WorkPriority;
use App\Enums\WorkTaskStatus;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Work\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int|null $project_id
 * @property int|null $objective_id
 * @property int|null $parent_id
 * @property int $assignee_id
 * @property int $created_by
 * @property string $title
 * @property CarbonImmutable $due_date
 * @property WorkPriority $priority
 * @property WorkTaskStatus $status
 * @property User $assignee
 * @property Project|null $project
 * @property Objective|null $objective
 * @property Task|null $parent
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    protected $table = 'work_tasks';

    /** @var list<string> */
    protected $fillable = ['project_id', 'objective_id', 'parent_id', 'assignee_id', 'created_by', 'title', 'due_date', 'priority', 'status'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Objective, $this> */
    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Task, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return MorphMany<WorkComment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(WorkComment::class, 'commentable');
    }

    /** @return MorphMany<WorkLink, $this> */
    public function links(): MorphMany
    {
        return $this->morphMany(WorkLink::class, 'linkable');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['due_date' => 'immutable_date', 'priority' => WorkPriority::class, 'status' => WorkTaskStatus::class];
    }
}
