<?php

namespace App\Models\Accountability;

use App\Enums\InternshipEvaluationType;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\InternshipEvaluationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $internship_id
 * @property int $evaluator_id
 * @property InternshipEvaluationType $type
 * @property CarbonImmutable|null $week_start_date
 * @property string $observed_progress
 * @property string|null $evidence
 * @property string|null $next_steps
 * @property bool|null $certificate_conditions_met
 * @property CarbonImmutable|null $validated_at
 * @property CarbonImmutable $created_at
 */
class InternshipEvaluation extends Model
{
    /** @use HasFactory<InternshipEvaluationFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'internship_id', 'evaluator_id', 'type', 'week_start_date', 'observed_progress',
        'evidence', 'next_steps', 'certificate_conditions_met', 'validated_at',
    ];

    /** @return BelongsTo<Internship, $this> */
    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }

    /** @return BelongsTo<User, $this> */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    /**
     * Une évaluation validée n'est ni modifiable ni supprimable (AC 32).
     */
    public function isEditable(): bool
    {
        return $this->validated_at === null;
    }

    /**
     * @param  Builder<InternshipEvaluation>  $query
     * @return Builder<InternshipEvaluation>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('direction')) {
            return $query;
        }

        return $query->whereHas(
            'internship',
            fn (Builder $internships): Builder => $internships->where(
                fn (Builder $visible): Builder => $visible
                    ->where('user_id', $user->getKey())
                    ->orWhere('tutor_id', $user->getKey()),
            ),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => InternshipEvaluationType::class,
            'week_start_date' => 'immutable_date',
            'certificate_conditions_met' => 'boolean',
            'validated_at' => 'immutable_datetime',
        ];
    }
}
