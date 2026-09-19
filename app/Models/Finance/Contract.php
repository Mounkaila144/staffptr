<?php

namespace App\Models\Finance;

use App\Enums\ContractState;
use App\Models\Identity\User;
use App\Models\Work\Project;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $expected_total_amount
 * @property int $forecast_profit_amount
 * @property string $reference
 * @property string $title
 * @property bool $has_execution
 * @property ContractState $state
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property Client $client
 * @property Project|null $project
 * @property User|null $contributor
 */
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'project_id',
        'reference',
        'title',
        'expected_total_amount',
        'forecast_profit_amount',
        'contributor_id',
        'has_execution',
        'state',
        'starts_on',
        'ends_on',
        'closed_at',
        'closure_reason',
        'cancellation_reason',
    ];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributor_id');
    }

    /** @return HasMany<ContractExecutor, $this> */
    public function executors(): HasMany
    {
        return $this->hasMany(ContractExecutor::class)
            ->where('is_active', true)
            ->orderBy('position');
    }

    /** @return HasMany<ContractExecutor, $this> */
    public function executorHistory(): HasMany
    {
        return $this->hasMany(ContractExecutor::class)->orderBy('created_at');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<ShareEntitlement, $this> */
    public function shareEntitlements(): HasMany
    {
        return $this->hasMany(ShareEntitlement::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => ContractState::class,
            'expected_total_amount' => 'integer',
            'forecast_profit_amount' => 'integer',
            'has_execution' => 'boolean',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
