<?php

namespace App\Models\Identity;

use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\LoginAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $successful
 * @property string $phone_attempted
 * @property string $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $lock_expires_at
 * @property User|null $user
 */
class LoginAttempt extends Model
{
    /** @use HasFactory<LoginAttemptFactory> */
    use HasFactory, PreventsPhysicalDeletion;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'phone_attempted',
        'successful',
        'ip_address',
        'user_agent',
        'occurred_at',
        'lock_expires_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'occurred_at' => 'immutable_datetime',
            'lock_expires_at' => 'immutable_datetime',
        ];
    }
}
