<?php

namespace App\Models\Accountability;

use App\Enums\ImprovementPlanState;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\ImprovementPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dispositif de soutien. Sa clôture ne produit aucune conséquence sur le compte,
 * le rôle, les permissions ou les sessions de la personne concernée (AC 12, RM-18).
 *
 * @property int $weekly_review_id
 * @property int $subject_user_id
 * @property int $created_by
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property string $support_provided
 * @property string|null $observed_result
 * @property ImprovementPlanState $state
 * @property int|null $closed_by
 * @property CarbonImmutable|null $closed_at
 */
class ImprovementPlan extends Model
{
    /** @use HasFactory<ImprovementPlanFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** Durée minimale d'un plan, en jours (AC 9). */
    public const MINIMUM_DURATION_DAYS = 7;

    /** Durée maximale d'un plan, en jours (AC 9). */
    public const MAXIMUM_DURATION_DAYS = 14;

    /** @var list<string> */
    protected $fillable = [
        'weekly_review_id', 'subject_user_id', 'created_by', 'start_date', 'end_date',
        'support_provided', 'observed_result', 'state', 'closed_by', 'closed_at',
    ];

    /** @return BelongsTo<WeeklyReview, $this> */
    public function weeklyReview(): BelongsTo
    {
        return $this->belongsTo(WeeklyReview::class);
    }

    /** @return BelongsTo<User, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ImprovementPlanAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ImprovementPlanAction::class);
    }

    /**
     * Visible de la personne concernée, de son responsable et de `direction`, et de personne
     * d'autre (AC 11).
     *
     * @param  Builder<ImprovementPlan>  $query
     * @return Builder<ImprovementPlan>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('direction')) {
            return $query;
        }

        return $query->where(fn (Builder $visible): Builder => $visible
            ->where('subject_user_id', $user->getKey())
            ->orWhereHas('subject', fn (Builder $team): Builder => $team->where('manager_id', $user->getKey())));
    }

    /**
     * Durée du plan en jours, bornes incluses.
     */
    public function durationInDays(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'state' => ImprovementPlanState::class,
            'closed_at' => 'immutable_datetime',
        ];
    }
}
