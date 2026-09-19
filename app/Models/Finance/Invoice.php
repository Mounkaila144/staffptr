<?php

namespace App\Models\Finance;

use App\Enums\InvoiceState;
use App\Enums\PaymentState;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $number
 * @property int $total_amount
 * @property InvoiceState $state
 * @property CarbonImmutable $issued_on
 * @property CarbonImmutable $due_on
 * @property CarbonImmutable|null $cancelled_at
 * @property Client $client
 * @property Contract $contract
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'contract_id',
        'number',
        'total_amount',
        'issued_on',
        'due_on',
        'cancellation_reason',
        'cancelled_at',
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

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function outstandingAmount(): int
    {
        $paid = (int) $this->payments()
            ->where('state', PaymentState::Validated->value)
            ->whereNull('reversal_of_id')
            ->sum('received_amount');

        return max(0, (int) $this->total_amount - $paid);
    }

    public function derivedState(): InvoiceState
    {
        if ($this->cancelled_at !== null) {
            return InvoiceState::Annulee;
        }

        $outstanding = $this->outstandingAmount();
        if ($outstanding === 0) {
            return InvoiceState::Payee;
        }

        return $outstanding < $this->total_amount
            ? InvoiceState::PartiellementPayee
            : InvoiceState::Impayee;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => InvoiceState::class,
            'total_amount' => 'integer',
            'issued_on' => 'immutable_date',
            'due_on' => 'immutable_date',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
