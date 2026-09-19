<?php

namespace App\Models\Finance;

use App\Enums\ReserveMovementType;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\ReserveMovementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $movement_amount
 * @property string $approval_state
 * @property ReserveMovementType $type
 * @property CarbonImmutable $occurred_on
 * @property int $created_by
 * @property int|null $first_approved_by
 * @property int|null $second_approved_by
 * @property string|null $reason
 * @property string|null $reconstitution_plan
 */
class ReserveMovement extends Model
{
    /** @use HasFactory<ReserveMovementFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'type',
        'movement_amount',
        'payment_id',
        'expense_id',
        'reversal_of_id',
        'occurred_on',
        'reason',
        'reconstitution_plan',
        'first_approved_by',
        'second_approved_by',
        'created_by',
        'idempotency_key',
        'approval_state',
        'requested_at',
        'approved_at',
    ];

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** @return BelongsTo<User, $this> */
    public function firstApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function secondApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_approved_by');
    }

    /**
     * @param  Builder<ReserveMovement>  $query
     * @return Builder<ReserveMovement>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasAnyRole(['direction', 'finance']) ? $query : $query->whereRaw('1 = 0');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ReserveMovementType::class,
            'movement_amount' => 'integer',
            'occurred_on' => 'immutable_date',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
        ];
    }
}
