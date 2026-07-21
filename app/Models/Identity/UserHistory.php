<?php

namespace App\Models\Identity;

use App\Support\Auditing\Immutable;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\UserHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property string $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property int|null $changed_by
 * @property CarbonImmutable $changed_at
 * @property string|null $reason
 * @property User $user
 * @property User|null $changedBy
 */
class UserHistory extends Model
{
    /** @use HasFactory<UserHistoryFactory> */
    use HasFactory, Immutable;

    public $timestamps = false;

    protected $table = 'user_history';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'field',
        'old_value',
        'new_value',
        'changed_by',
        'changed_at',
        'reason',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['changed_at' => 'immutable_datetime'];
    }
}
