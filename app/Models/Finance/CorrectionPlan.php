<?php

namespace App\Models\Finance;

use App\Enums\CorrectionPlanState;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\CorrectionPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Plan correctif rattaché au mois qui a déclenché le niveau orange (AC 14 à 18, FR163).
 *
 * @property CarbonImmutable $month
 * @property int $version
 * @property int|null $previous_id
 * @property string $finding
 * @property string $actions
 * @property string $responsibles
 * @property CarbonImmutable $due_on
 * @property string $expected_result
 * @property CorrectionPlanState $state
 * @property CarbonImmutable|null $validated_at
 * @property string|null $revision_reason
 */
class CorrectionPlan extends Model
{
    /** @use HasFactory<CorrectionPlanFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'month',
        'version',
        'previous_id',
        'finding',
        'actions',
        'responsibles',
        'due_on',
        'expected_result',
        'state',
        'created_by',
        'validated_by',
        'validated_at',
        'revision_reason',
    ];

    /** @return BelongsTo<CorrectionPlan, $this> */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_id');
    }

    /** @return HasOne<CorrectionPlan, $this> */
    public function revision(): HasOne
    {
        return $this->hasOne(self::class, 'previous_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Version courante d'un mois : le numéro de version le plus élevé. Aucune ligne antérieure
     * n'est jamais réécrite (AC 17).
     *
     * @param  Builder<CorrectionPlan>  $query
     * @return Builder<CorrectionPlan>
     */
    public function scopeForMonth(Builder $query, CarbonImmutable $month): Builder
    {
        return $query->whereDate('month', $month->startOfMonth()->toDateString());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'month' => 'immutable_date',
            'version' => 'integer',
            'due_on' => 'immutable_date',
            'state' => CorrectionPlanState::class,
            'validated_at' => 'immutable_datetime',
        ];
    }
}
