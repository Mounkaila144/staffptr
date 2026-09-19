<?php

namespace App\Models\Work;

use App\Models\Identity\User;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Work\WorkLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $label
 * @property string $url
 * @property int $created_by
 */
class WorkLink extends Model
{
    /** @use HasFactory<WorkLinkFactory> */
    use HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['linkable_type', 'linkable_id', 'label', 'url', 'created_by'];

    /** @return MorphTo<Model, $this> */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
