<?php

namespace App\Models\Finance;

use App\Enums\FinancialMovementDirection;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Finance\AccountMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMovement extends Model
{
    /** @use HasFactory<AccountMovementFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'account_id',
        'direction',
        'movement_amount',
        'effective_on',
        'source_type',
        'source_id',
        'description',
        'reversal_of_id',
        'created_by',
        'validated_at',
    ];

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<AccountMovement, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => FinancialMovementDirection::class,
            'movement_amount' => 'integer',
            'effective_on' => 'immutable_date',
            'validated_at' => 'immutable_datetime',
        ];
    }
}
