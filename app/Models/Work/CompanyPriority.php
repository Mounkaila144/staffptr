<?php

namespace App\Models\Work;

use App\Enums\CompanyPriorityState;
use App\Enums\WorkPriority;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Work\CompanyPriorityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property CarbonImmutable $month
 * @property string $title
 * @property string $description
 * @property int $owner_id
 * @property string $indicator
 * @property string $target
 * @property CarbonImmutable $due_date
 * @property WorkPriority $priority
 * @property CompanyPriorityState $state
 * @property string|null $cancellation_reason
 * @property User $owner
 */
class CompanyPriority extends Model
{
    /** @use HasFactory<CompanyPriorityFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['month', 'title', 'description', 'owner_id', 'indicator', 'target', 'due_date', 'priority', 'state', 'cancellation_reason'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<Objective, $this> */
    public function objectives(): HasMany
    {
        return $this->hasMany(Objective::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForMonth(Builder $query, CarbonImmutable $month): Builder
    {
        return $query->whereDate('month', $month->startOfMonth()->toDateString());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['month' => 'immutable_date', 'due_date' => 'immutable_date', 'priority' => WorkPriority::class, 'state' => CompanyPriorityState::class];
    }
}
