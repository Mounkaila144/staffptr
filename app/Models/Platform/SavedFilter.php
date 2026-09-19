<?php

namespace App\Models\Platform;

use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Platform\SavedFilterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Filtre enregistré, privé à son auteur (AC 8).
 *
 * @property string $list_key
 * @property string $name
 * @property array<string, mixed> $criteria
 * @property bool $is_active
 * @property CarbonImmutable|null $deactivated_at
 */
class SavedFilter extends Model
{
    /** @use HasFactory<SavedFilterFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'owner_id',
        'list_key',
        'name',
        'criteria',
        'is_active',
        'deactivated_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Périmètre de visibilité : **son auteur, et personne d'autre** (AC 8).
     *
     * Il n'y a délibérément aucune exception pour `direction` ou `super_admin`. Un filtre décrit
     * ce que son auteur cherche ; le rendre lisible par un tiers en ferait un canal d'information
     * sur son travail, ce que le produit s'interdit (SOC-10, vocabulaire de contribution et non de
     * surveillance).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('owner_id', $user->getKey());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'is_active' => 'boolean',
            'deactivated_at' => 'immutable_datetime',
        ];
    }
}
