<?php

namespace App\Models\Finance;

use App\Models\Identity\User;
use Database\Factories\Finance\ExpenseApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $expense_id
 * @property int $approver_id
 * @property string $decision
 * @property string|null $comment
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @mixin \Eloquent
 */
class ExpenseApproval extends Model
{
    /** @use HasFactory<ExpenseApprovalFactory> */
    use HasFactory;

    public const DECISION_APPROVE = 'approve';

    public const DECISION_REJECT = 'reject';

    public $timestamps = false;

    protected $fillable = [
        'expense_id',
        'approver_id',
        'decision',
        'comment',
        'decided_at',
    ];

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime:Y-m-d H:i:s',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }
}
