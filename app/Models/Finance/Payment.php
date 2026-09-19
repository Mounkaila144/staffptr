<?php

namespace App\Models\Finance;

use App\Enums\PaymentMode;
use App\Enums\PaymentState;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Work\Project;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property int $received_amount
 * @property PaymentState $state
 * @property PaymentMode $payment_mode
 * @property CarbonImmutable $received_on
 * @property CarbonImmutable|null $received_at
 * @property bool $late_recording
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'receipt_number',
        'receipt_sequence_id',
        'client_id',
        'contract_id',
        'project_id',
        'invoice_id',
        'account_id',
        'received_amount',
        'received_on',
        'received_at',
        'payment_mode',
        'reference',
        'state',
        'correction_of_id',
        'reversal_of_id',
        'correction_reason',
        'cancellation_reason',
        'late_recording',
        'post_reopening',
        'idempotency_key',
        'created_by',
    ];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ShareEntitlement, $this> */
    public function shareEntitlements(): HasMany
    {
        return $this->hasMany(ShareEntitlement::class);
    }

    /** @return MorphOne<Attachment, $this> */
    public function attachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => PaymentState::class,
            'payment_mode' => PaymentMode::class,
            'received_amount' => 'integer',
            'received_on' => 'immutable_date',
            'received_at' => 'immutable_datetime',
            'late_recording' => 'boolean',
            'post_reopening' => 'boolean',
        ];
    }
}
