<?php

namespace App\Models\Work;

use App\Models\Identity\User;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Work\WorkCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $author_id
 * @property string $body
 * @property bool $correction_requested
 * @property User $author
 */
class WorkComment extends Model
{
    /** @use HasFactory<WorkCommentFactory> */
    use HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['commentable_type', 'commentable_id', 'author_id', 'body', 'correction_requested'];

    /** @return MorphTo<Model, $this> */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['correction_requested' => 'boolean'];
    }
}
