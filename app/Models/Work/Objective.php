<?php

namespace App\Models\Work;

use App\Enums\ObjectiveState;
use App\Enums\WorkPriority;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Work\ObjectiveFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $created_by
 * @property int|null $company_priority_id
 * @property int|null $project_id
 * @property string $title
 * @property string $description
 * @property string $indicator
 * @property string $target_value
 * @property string $expected_evidence
 * @property string|null $required_means
 * @property CarbonImmutable $due_date
 * @property WorkPriority $priority
 * @property ObjectiveState $state
 * @property int $progress
 * @property int $version_number
 * @property User $owner
 * @property Project|null $project
 */
class Objective extends Model
{
    /** @use HasFactory<ObjectiveFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['user_id', 'created_by', 'company_priority_id', 'project_id', 'title', 'description', 'indicator', 'target_value', 'expected_evidence', 'required_means', 'due_date', 'priority', 'state', 'progress', 'version_number'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<CompanyPriority, $this> */
    public function companyPriority(): BelongsTo
    {
        return $this->belongsTo(CompanyPriority::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<ObjectiveVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ObjectiveVersion::class);
    }

    /** @return MorphMany<WorkComment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(WorkComment::class, 'commentable');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('direction')) {
            return $query;
        }
        if ($user->hasRole('tuteur')) {
            return $query->where(fn (Builder $scope): Builder => $scope->where('user_id', $user->getKey())->orWhereHas('owner', fn (Builder $owners): Builder => $owners->where('manager_id', $user->getKey())));
        }

        return $query->where('user_id', $user->getKey());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDueInMonth(Builder $query, CarbonImmutable $month): Builder
    {
        return $query->whereDate('due_date', '>=', $month->startOfMonth()->toDateString())
            ->whereDate('due_date', '<=', $month->endOfMonth()->toDateString());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['due_date' => 'immutable_date', 'priority' => WorkPriority::class, 'state' => ObjectiveState::class, 'progress' => 'integer', 'version_number' => 'integer'];
    }
}
