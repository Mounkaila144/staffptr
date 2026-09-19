<?php

namespace App\Models\Accountability;

use App\Enums\WeeklyReviewState;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\WeeklyReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $subject_user_id
 * @property int $reviewer_id
 * @property CarbonImmutable $week_start_date
 * @property CarbonImmutable $scheduled_on
 * @property WeeklyReviewState $state
 * @property string|null $reviewee_comment
 * @property string|null $reviewer_comment
 * @property int|null $reviewee_validated_by
 * @property CarbonImmutable|null $reviewee_validated_at
 * @property int|null $reviewer_validated_by
 * @property CarbonImmutable|null $reviewer_validated_at
 * @property CarbonImmutable $created_at
 */
class WeeklyReview extends Model
{
    /** @use HasFactory<WeeklyReviewFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'subject_user_id', 'reviewer_id', 'week_start_date', 'scheduled_on', 'state',
        'reviewee_comment', 'reviewer_comment', 'reviewee_validated_by', 'reviewee_validated_at',
        'reviewer_validated_by', 'reviewer_validated_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revieweeValidator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_validated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewerValidator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_validated_by');
    }

    /** @return HasMany<WeeklyReviewObjective, $this> */
    public function objectiveEntries(): HasMany
    {
        return $this->hasMany(WeeklyReviewObjective::class);
    }

    /** @return HasMany<ImprovementPlan, $this> */
    public function improvementPlans(): HasMany
    {
        return $this->hasMany(ImprovementPlan::class);
    }

    /**
     * Une revue est visible de la personne évaluée, de son responsable et de `direction` (AC 5, 11).
     *
     * @param  Builder<WeeklyReview>  $query
     * @return Builder<WeeklyReview>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('direction')) {
            return $query;
        }

        return $query->where(fn (Builder $visible): Builder => $visible
            ->where('subject_user_id', $user->getKey())
            ->orWhere('reviewer_id', $user->getKey()));
    }

    /**
     * La revue est validée dès que les deux parties se sont prononcées (AC 4).
     */
    public function hasBothValidations(): bool
    {
        return $this->reviewee_validated_at !== null && $this->reviewer_validated_at !== null;
    }

    /**
     * Une revue validée est figée (AC 7).
     */
    public function isEditable(): bool
    {
        return $this->state->isEditable();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'week_start_date' => 'immutable_date',
            'scheduled_on' => 'immutable_date',
            'state' => WeeklyReviewState::class,
            'reviewee_validated_at' => 'immutable_datetime',
            'reviewer_validated_at' => 'immutable_datetime',
        ];
    }
}
