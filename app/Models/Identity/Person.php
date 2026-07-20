<?php

namespace App\Models\Identity;

use App\Enums\PersonOperationalStatus;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Identity\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'full_name',
        'operational_status',
        'first_seen_at',
    ];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
