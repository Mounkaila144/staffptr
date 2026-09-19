<?php

namespace App\Models\Accountability;

use App\Enums\ReviewObjectiveStatus;
use App\Models\Work\Objective;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\WeeklyReviewObjectiveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $weekly_review_id
 * @property int $objective_id
 * @property string $result
 * @property string|null $evidence
 * @property ReviewObjectiveStatus $status
 * @property string|null $gap_cause
 * @property string $next_action
 * @property CarbonImmutable $created_at
 */
class WeeklyReviewObjective extends Model
{
    /** @use HasFactory<WeeklyReviewObjectiveFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'weekly_review_id', 'objective_id', 'result', 'evidence', 'status', 'gap_cause', 'next_action',
    ];

    /** @return BelongsTo<WeeklyReview, $this> */
    public function weeklyReview(): BelongsTo
    {
        return $this->belongsTo(WeeklyReview::class);
    }

    /** @return BelongsTo<Objective, $this> */
    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReviewObjectiveStatus::class,
        ];
    }
}
