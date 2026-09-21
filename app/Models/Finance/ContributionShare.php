<?php

namespace App\Models\Finance;

use App\Enums\ContributionEntryType;
use App\Enums\ContributionOrigin;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\ContributionShareFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne du registre des parts de contribution — une propriété permanente dans l'entreprise.
 *
 * À ne pas confondre avec {@see ShareEntitlement}, qui désigne l'argent dû à une personne sur un
 * encaissement. Une part de contribution ne se verse pas et ne s'efface pas : elle mesure ce que
 * son détenteur a fait entrer dans l'entreprise.
 *
 * @property int $base_amount
 * @property int $issued_shares
 * @property int $vintage_year
 * @property int $coefficient_basis_points
 * @property ContributionOrigin $origin
 * @property ContributionEntryType $entry_type
 * @property CarbonImmutable $occurred_on
 * @property string $reason
 * @property User $holder
 * @property Contract|null $contract
 * @property Payment|null $payment
 * @property ShareEntitlement|null $shareEntitlement
 * @property CapitalContribution|null $capitalContribution
 */
class ContributionShare extends Model
{
    /** @use HasFactory<ContributionShareFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'holder_id',
        'origin',
        'entry_type',
        'share_entitlement_id',
        'payment_id',
        'contract_id',
        'capital_contribution_id',
        'reversal_of_id',
        'base_amount',
        'vintage_year',
        'coefficient_basis_points',
        'issued_shares',
        'occurred_on',
        'reason',
        'created_by',
    ];

    /** @return BelongsTo<User, $this> */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'holder_id');
    }

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

    /** @return BelongsTo<ShareEntitlement, $this> */
    public function shareEntitlement(): BelongsTo
    {
        return $this->belongsTo(ShareEntitlement::class);
    }

    /** @return BelongsTo<CapitalContribution, $this> */
    public function capitalContribution(): BelongsTo
    {
        return $this->belongsTo(CapitalContribution::class);
    }

    /**
     * Parts portées par la ligne, signées : une annulation retranche ce qu'une émission a ajouté.
     */
    public function signedShares(): int
    {
        return $this->entry_type === ContributionEntryType::Annulation
            ? -$this->issued_shares
            : $this->issued_shares;
    }

    /** Assiette portée par la ligne, signée selon le même principe. */
    public function signedBaseAmount(): int
    {
        return $this->entry_type === ContributionEntryType::Annulation
            ? -$this->base_amount
            : $this->base_amount;
    }

    /**
     * @param  Builder<ContributionShare>  $query
     * @return Builder<ContributionShare>
     */
    public function scopeOfOrigin(Builder $query, ContributionOrigin $origin): Builder
    {
        return $query->where('origin', $origin->value);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'origin' => ContributionOrigin::class,
            'entry_type' => ContributionEntryType::class,
            'base_amount' => 'integer',
            'vintage_year' => 'integer',
            'coefficient_basis_points' => 'integer',
            'issued_shares' => 'integer',
            'occurred_on' => 'immutable_date',
        ];
    }
}
