<?php

namespace App\Models\Identity;

use App\Enums\UserState;
use App\Support\Auditing\Auditable;
use App\Support\PhoneNumber;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Identity\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'person_id',
        'phone',
        'password',
        'state',
        'must_change_password',
        'locked_until',
        'failed_attempts',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
    ];

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Cette portée constitue le contrat commun des index et exports de comptes.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['super_admin', 'direction'])) {
            return $query;
        }

        return $query->where('person_id', $user->person_id);
    }

    /** @return Attribute<string, string> */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => PhoneNumber::normalize($value),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => UserState::class,
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'locked_until' => 'immutable_datetime',
            'failed_attempts' => 'integer',
        ];
    }
}
