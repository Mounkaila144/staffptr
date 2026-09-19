<?php

namespace App\Models\Finance;

use App\Enums\FinancialAccountState;
use App\Enums\FinancialAccountType;
use App\Enums\FinancialMovementDirection;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property FinancialAccountType $type
 * @property FinancialAccountState $state
 * @property string $label
 * @property int $opening_balance_amount
 * @property CarbonImmutable $opening_balance_date
 * @property string|null $deactivation_reason
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'type',
        'label',
        'opening_balance_amount',
        'opening_balance_date',
        'state',
        'deactivation_reason',
        'deactivated_at',
    ];

    /** @return HasMany<AccountMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(AccountMovement::class);
    }

    public function currentBalance(): int
    {
        $credits = (int) $this->movements()
            ->where('direction', FinancialMovementDirection::Credit->value)
            ->sum('movement_amount');
        $debits = (int) $this->movements()
            ->where('direction', FinancialMovementDirection::Debit->value)
            ->sum('movement_amount');

        return (int) $this->opening_balance_amount + $credits - $debits;
    }

    public function balanceAt(string $date): int
    {
        $credits = (int) $this->movements()->whereDate('effective_on', '<=', $date)
            ->where('direction', FinancialMovementDirection::Credit->value)->sum('movement_amount');
        $debits = (int) $this->movements()->whereDate('effective_on', '<=', $date)
            ->where('direction', FinancialMovementDirection::Debit->value)->sum('movement_amount');

        return (int) $this->opening_balance_amount + $credits - $debits;
    }

    /**
     * @param  Builder<Account>  $query
     * @return Builder<Account>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasAnyRole(['direction', 'finance'])
            ? $query
            : $query->whereRaw('1 = 0');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => FinancialAccountType::class,
            'state' => FinancialAccountState::class,
            'opening_balance_amount' => 'integer',
            'opening_balance_date' => 'immutable_date',
            'deactivated_at' => 'immutable_datetime',
        ];
    }
}
