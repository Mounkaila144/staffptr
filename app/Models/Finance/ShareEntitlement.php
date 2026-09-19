<?php

namespace App\Models\Finance;

use App\Enums\ShareType;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\ShareEntitlementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $base_amount
 * @property int $share_amount
 * @property int $paid_amount
 * @property int|null $beneficiary_id
 * @property string $beneficiary_key
 * @property ShareType $share_type
 * @property int $rate_basis_points
 * @property int $rate_divisor
 * @property string $calculation_method
 * @property CarbonImmutable|null $reversed_at
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property CarbonImmutable $source_received_on
 * @property Contract $contract
 * @property Payment $payment
 * @property User|null $beneficiary
 */
class ShareEntitlement extends Model
{
    /** @use HasFactory<ShareEntitlementFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'contract_id',
        'payment_id',
        'beneficiary_id',
        'beneficiary_key',
        'share_type',
        'base_amount',
        'rate_basis_points',
        'rate_divisor',
        'share_amount',
        'paid_amount',
        'reversed_at',
        'reversal_payment_id',
        'calculation_method',
        'period_start',
        'period_end',
        'source_received_on',
    ];

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    /**
     * @param  Builder<ShareEntitlement>  $query
     * @return Builder<ShareEntitlement>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['direction', 'finance'])) {
            return $query;
        }

        return $query->where('beneficiary_id', $user->getKey());
    }

    public function remainingAmount(): int
    {
        return max(0, (int) $this->share_amount - (int) $this->paid_amount);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'share_type' => ShareType::class,
            'base_amount' => 'integer',
            'rate_basis_points' => 'integer',
            'rate_divisor' => 'integer',
            'share_amount' => 'integer',
            'paid_amount' => 'integer',
            'reversed_at' => 'immutable_datetime',
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'source_received_on' => 'immutable_date',
        ];
    }
}
