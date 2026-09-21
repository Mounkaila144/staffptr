<?php

namespace App\Models\Finance;

use App\Enums\CapitalContributionState;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\CapitalContributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Apport d'argent personnel d'un directeur, définitif une fois approuvé : aucun remboursement
 * n'existe dans le système, par décision de la direction (story 12.1).
 *
 * @property int $contribution_amount
 * @property CapitalContributionState $state
 * @property string $purpose
 * @property CarbonImmutable $declared_on
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $refused_at
 * @property string|null $refusal_reason
 * @property User $contributor
 * @property User|null $approver
 * @property User|null $refuser
 */
class CapitalContribution extends Model
{
    /** @use HasFactory<CapitalContributionFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'contributor_id',
        'contribution_amount',
        'declared_on',
        'state',
        'purpose',
        'approved_by',
        'approved_at',
        'refused_by',
        'refused_at',
        'refusal_reason',
        'idempotency_key',
    ];

    /** @return BelongsTo<User, $this> */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function refuser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refused_by');
    }

    /** @return HasMany<ContributionShare, $this> */
    public function contributionShares(): HasMany
    {
        return $this->hasMany(ContributionShare::class);
    }

    public function isPending(): bool
    {
        return $this->state === CapitalContributionState::EnAttente;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => CapitalContributionState::class,
            'contribution_amount' => 'integer',
            'declared_on' => 'immutable_date',
            'approved_at' => 'immutable_datetime',
            'refused_at' => 'immutable_datetime',
        ];
    }
}
