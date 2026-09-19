<?php

namespace App\Models\Work;

use App\Enums\DeliverableStatus;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Work\DeliverableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property int $owner_id
 * @property string $title
 * @property CarbonImmutable $planned_date
 * @property CarbonImmutable|null $actual_date
 * @property DeliverableStatus $status
 * @property Project $project
 * @property User $owner
 */
class Deliverable extends Model
{
    /** @use HasFactory<DeliverableFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['project_id', 'owner_id', 'title', 'planned_date', 'actual_date', 'status'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<DeliverableStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(DeliverableStatusHistory::class);
    }

    public function scheduleVarianceDays(): ?int
    {
        return $this->actual_date?->diffInDays($this->planned_date, false) * -1;
    }

    public function scheduleVarianceLabel(): string
    {
        $days = $this->scheduleVarianceDays();

        return self::varianceLabel($days);
    }

    public static function varianceLabel(?int $days): string
    {
        if ($days === null) {
            return 'Date réelle non renseignée';
        }
        if ($days === 0) {
            return 'Livré à la date prévue';
        }
        $amount = abs($days);

        return $days > 0 ? "{$amount} jour".($amount > 1 ? 's' : '').' de retard' : "{$amount} jour".($amount > 1 ? 's' : '')." d'avance";
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['planned_date' => 'immutable_date', 'actual_date' => 'immutable_date', 'status' => DeliverableStatus::class];
    }
}
