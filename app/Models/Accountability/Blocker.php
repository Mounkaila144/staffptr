<?php

namespace App\Models\Accountability;

use App\Enums\BlockerState;
use App\Enums\BlockerUrgency;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\BlockerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property BlockerState $state
 * @property BlockerUrgency $urgency
 * @property string $problem
 * @property int $created_by
 * @property int $solicited_user_id
 * @property CarbonImmutable $reported_on
 * @property string $deadline_impact
 * @property string $attempted_action
 * @property string|null $closure_reason
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $acknowledged_at
 * @property CarbonImmutable|null $resolved_at
 */
class Blocker extends Model
{
    /** @use HasFactory<BlockerFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'origin_type', 'origin_id', 'created_by', 'solicited_user_id', 'problem', 'urgency',
        'reported_on', 'deadline_impact', 'attempted_action', 'state', 'acknowledged_at',
        'resolved_at', 'closure_reason',
    ];

    /** @return MorphTo<Model, $this> */
    public function origin(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function solicitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicited_user_id');
    }

    /**
     * @param  Builder<Blocker>  $query
     * @return Builder<Blocker>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('direction')) {
            return $query;
        }

        return $query->where(fn (Builder $visible): Builder => $visible
            ->where('created_by', $user->getKey())
            ->orWhere('solicited_user_id', $user->getKey()));
    }

    public function acknowledgementDelayMinutes(): ?int
    {
        return $this->acknowledged_at === null ? null : (int) $this->acknowledged_at->diffInMinutes($this->created_at, true);
    }

    public function resolutionDelayMinutes(): ?int
    {
        return $this->resolved_at === null ? null : (int) $this->resolved_at->diffInMinutes($this->created_at, true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'urgency' => BlockerUrgency::class,
            'reported_on' => 'immutable_date',
            'state' => BlockerState::class,
            'acknowledged_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
