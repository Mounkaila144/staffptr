<?php

namespace App\Models\Identity;

use App\Enums\AbsenceState;
use App\Enums\AbsenceType;
use App\Models\Platform\Attachment;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\AbsenceFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property int $user_id
 * @property AbsenceType $type
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property string $reason
 * @property AbsenceState $state
 * @property string|null $decision_reason
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property User $user
 * @property User|null $decidedBy
 * @property Attachment|null $attachment
 */
class Absence extends Model
{
    /** @use HasFactory<AbsenceFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'end_date',
        'reason',
        'state',
        'decision_reason',
        'decided_by',
        'decided_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return MorphOne<Attachment, $this> */
    public function attachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }

    /**
     * @param  Builder<Absence>  $query
     * @return Builder<Absence>
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole('direction')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($actor): void {
            $visible->where('user_id', $actor->getKey())
                ->orWhereHas('user', fn (Builder $user): Builder => $user->where('manager_id', $actor->getKey()));
        });
    }

    /**
     * @param  Builder<Absence>  $query
     * @return Builder<Absence>
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from);
    }

    public function isPending(): bool
    {
        return $this->state === AbsenceState::Demandee;
    }

    /** @return Attribute<CarbonImmutable, DateTimeInterface|string> */
    protected function startDate(): Attribute
    {
        return Attribute::make(set: fn (DateTimeInterface|string $value): string => $this->civilDate($value));
    }

    /** @return Attribute<CarbonImmutable, DateTimeInterface|string> */
    protected function endDate(): Attribute
    {
        return Attribute::make(set: fn (DateTimeInterface|string $value): string => $this->civilDate($value));
    }

    private function civilDate(DateTimeInterface|string $value): string
    {
        return $value instanceof DateTimeInterface
            ? $value->format('Y-m-d')
            : CarbonImmutable::parse($value, 'Africa/Niamey')->format('Y-m-d');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'type' => AbsenceType::class,
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'state' => AbsenceState::class,
            'decided_by' => 'integer',
            'decided_at' => 'immutable_datetime',
        ];
    }
}
