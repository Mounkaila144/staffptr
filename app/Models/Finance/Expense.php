<?php

namespace App\Models\Finance;

use App\Enums\ExpenseState;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Work\Project;
use App\Support\Auditing\Auditable;
use App\Support\Money;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Finance\ExpenseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $requester_id
 * @property int $category_id
 * @property string $reason
 * @property int $requested_amount
 * @property string $beneficiary
 * @property string $expected_result
 * @property string|null $project_or_contract_note
 * @property ExpenseState $state
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $paid_on
 * @property int|null $payment_attachment_id
 * @property bool $is_advance_reimbursement
 *
 * @mixin \Eloquent
 */
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'requester_id',
        'category_id',
        'reason',
        'requested_amount',
        'beneficiary',
        'expected_result',
        'project_or_contract_note',
        'cancel_reason',
        'account_id',
        'paid_on',
        'payment_mode',
        'payment_reference',
        'paid_by',
        'paid_at',
        'project_id',
        'contract_id',
        'beneficiary_user_id',
        'share_entitlement_id',
        'payment_attachment_id',
        'original_attachment_id',
        'is_advance_reimbursement',
        'counter_entry_of_id',
        'post_reopening',
        'payment_idempotency_key',
        'payment_cancellation_reason',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * @return HasMany<ExpenseApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(ExpenseApproval::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function beneficiaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    /** @return BelongsTo<ShareEntitlement, $this> */
    public function shareEntitlement(): BelongsTo
    {
        return $this->belongsTo(ShareEntitlement::class);
    }

    /** @return BelongsTo<Attachment, $this> */
    public function paymentAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'payment_attachment_id');
    }

    /** @return BelongsTo<Attachment, $this> */
    public function originalAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'original_attachment_id');
    }

    /** @return BelongsTo<Expense, $this> */
    public function counterEntryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'counter_entry_of_id');
    }

    /** @return HasMany<Expense, $this> */
    public function counterEntries(): HasMany
    {
        return $this->hasMany(self::class, 'counter_entry_of_id');
    }

    /**
     * @return MorphOne<Attachment, $this>
     */
    public function attachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }

    /**
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['direction', 'finance'])) {
            return $query; // direction and finance see everything
        }

        // Requesters see only their own expenses
        return $query->where('requester_id', $user->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => ExpenseState::class,
            'requested_amount' => 'integer',
            'paid_on' => 'immutable_date',
            'paid_at' => 'immutable_datetime',
            'is_advance_reimbursement' => 'boolean',
            'post_reopening' => 'boolean',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    /**
     * Format the amount for display using Money helper
     */
    public function formattedAmount(): string
    {
        return Money::from($this->requested_amount)->format();
    }

    /**
     * Contract consumed by the payment epic: only a fully approved expense is payable.
     */
    public function isPayable(): bool
    {
        return $this->state === ExpenseState::Approuvee;
    }
}
