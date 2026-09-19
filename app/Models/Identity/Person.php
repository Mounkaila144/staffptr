<?php

namespace App\Models\Identity;

use App\Enums\PersonOperationalStatus;
use App\Enums\UserState;
use App\Models\Platform\Attachment;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\PersonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string $full_name
 * @property PersonOperationalStatus $operational_status
 * @property CarbonImmutable $first_seen_at
 */
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'full_name',
        'photo_path',
        'operational_status',
        'first_seen_at',
    ];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @return HasMany<PersonDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(PersonDocument::class);
    }

    /**
     * Portée réutilisable par les index et leurs exports. La portée « équipe » sera ajoutée avec
     * le modèle d'organisation des epics 3 et 7 ; elle ne doit jamais être simulée côté client.
     *
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['super_admin', 'direction'])) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereKey($user->person_id)
                ->orWhereHas('users', function (Builder $team) use ($user): void {
                    $team->where('manager_id', $user->getKey())
                        ->where('state', UserState::Actif);
                });
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'operational_status' => PersonOperationalStatus::class,
            'first_seen_at' => 'immutable_date',
        ];
    }
}
