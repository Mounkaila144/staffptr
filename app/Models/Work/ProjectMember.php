<?php

namespace App\Models\Work;

use App\Models\Identity\User;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Work\ProjectMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property CarbonImmutable $joined_on
 * @property CarbonImmutable|null $left_on
 * @property int $added_by
 * @property int|null $removed_by
 * @property User $user
 */
class ProjectMember extends Model
{
    /** @use HasFactory<ProjectMemberFactory> */
    use HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['project_id', 'user_id', 'joined_on', 'left_on', 'added_by', 'removed_by'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['joined_on' => 'immutable_date', 'left_on' => 'immutable_date'];
    }
}
