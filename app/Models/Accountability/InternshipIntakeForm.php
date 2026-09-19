<?php

namespace App\Models\Accountability;

use App\Enums\InternshipIntakeState;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\InternshipIntakeFormFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $candidate_user_id
 * @property int $manager_id
 * @property int|null $tutor_id
 * @property string $real_need
 * @property string $mission
 * @property int $duration_weeks
 * @property string $tools
 * @property InternshipIntakeState $state
 * @property CarbonImmutable|null $submitted_at
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_reason
 */
class InternshipIntakeForm extends Model
{
    /** @use HasFactory<InternshipIntakeFormFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** Nombre de résultats attendus exigé à la soumission (AC 14). */
    public const REQUIRED_OUTCOMES = 3;

    /** @var list<string> */
    protected $fillable = [
        'candidate_user_id', 'manager_id', 'tutor_id', 'real_need', 'mission', 'duration_weeks',
        'tools', 'state', 'submitted_at', 'decided_by', 'decided_at', 'decision_reason',
    ];

    /** @return BelongsTo<User, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** @return BelongsTo<User, $this> */
    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return HasMany<InternshipIntakeOutcome, $this> */
    public function outcomes(): HasMany
    {
        return $this->hasMany(InternshipIntakeOutcome::class)->orderBy('position');
    }

    /**
     * @param  Builder<InternshipIntakeForm>  $query
     * @return Builder<InternshipIntakeForm>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('direction')) {
            return $query;
        }

        return $query->where(fn (Builder $visible): Builder => $visible
            ->where('candidate_user_id', $user->getKey())
            ->orWhere('manager_id', $user->getKey())
            ->orWhere('tutor_id', $user->getKey()));
    }

    public function isApproved(): bool
    {
        return $this->state === InternshipIntakeState::Approuvee;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => InternshipIntakeState::class,
            'duration_weeks' => 'integer',
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }
}
