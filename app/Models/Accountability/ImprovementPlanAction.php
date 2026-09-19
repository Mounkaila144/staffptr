<?php

namespace App\Models\Accountability;

use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\ImprovementPlanActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $improvement_plan_id
 * @property int $position
 * @property string $description
 * @property CarbonImmutable $due_date
 * @property CarbonImmutable|null $completed_at
 */
class ImprovementPlanAction extends Model
{
    /** @use HasFactory<ImprovementPlanActionFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['improvement_plan_id', 'position', 'description', 'due_date', 'completed_at'];

    /** @return BelongsTo<ImprovementPlan, $this> */
    public function improvementPlan(): BelongsTo
    {
        return $this->belongsTo(ImprovementPlan::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_date' => 'immutable_date',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
