<?php

namespace App\Models\Work;

use App\Enums\ProjectStatus;
use App\Models\Identity\User;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Work\ProjectStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $project_id
 * @property ProjectStatus|null $from_status
 * @property ProjectStatus $to_status
 * @property int $actor_id
 * @property string $reason
 * @property CarbonImmutable $changed_at
 */
class ProjectStatusHistory extends Model
{
    /** @use HasFactory<ProjectStatusHistoryFactory> */
    use HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['project_id', 'from_status', 'to_status', 'actor_id', 'reason', 'changed_at'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_status' => ProjectStatus::class, 'to_status' => ProjectStatus::class, 'changed_at' => 'immutable_datetime'];
    }
}
