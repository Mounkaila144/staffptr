<?php

namespace App\Models\Finance;

use App\Enums\FinancialMovementDirection;
use App\Enums\ReconciliationState;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\ReconciliationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $calculated_balance_amount
 * @property int $physical_balance_amount
 * @property int $difference_amount
 * @property int $prepared_by
 * @property int|null $controlled_by
 * @property int|null $previous_id
 * @property string|null $difference_explanation
 * @property string|null $corrective_action
 * @property string|null $correction_reason
 * @property ReconciliationState $state
 * @property FinancialMovementDirection|null $difference_direction
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property Account $account
 */
class Reconciliation extends Model
{
    /** @use HasFactory<ReconciliationFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'account_id',
        'period_start',
        'period_end',
        'calculated_balance_amount',
        'physical_balance_amount',
        'difference_direction',
        'difference_amount',
        'difference_explanation',
        'responsible_id',
        'corrective_action',
        'prepared_by',
        'controlled_by',
        'state',
        'validated_at',
        'previous_id',
        'correction_reason',
    ];

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<User, $this> */
    public function controller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controlled_by');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /** @return BelongsTo<Reconciliation, $this> */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_id');
    }

    /**
     * @param  Builder<Reconciliation>  $query
     * @return Builder<Reconciliation>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasAnyRole(['direction', 'finance']) ? $query : $query->whereRaw('1 = 0');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => ReconciliationState::class,
            'difference_direction' => FinancialMovementDirection::class,
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'calculated_balance_amount' => 'integer',
            'physical_balance_amount' => 'integer',
            'difference_amount' => 'integer',
            'validated_at' => 'immutable_datetime',
        ];
    }
}
