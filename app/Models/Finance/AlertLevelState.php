<?php

namespace App\Models\Finance;

use App\Enums\AlertLevel;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\AlertLevelStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Dernier niveau d'alerte recalculé pour un mois (AC 5 à 7, AC 11).
 *
 * @property CarbonImmutable $month
 * @property AlertLevel $level
 * @property int $baseline_amount
 * @property int $collections_amount
 * @property int $previous_collections_amount
 * @property CarbonImmutable $source_date
 * @property CarbonImmutable $observed_at
 * @property CarbonImmutable $calculated_at
 */
class AlertLevelState extends Model
{
    /** @use HasFactory<AlertLevelStateFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'month',
        'level',
        'baseline_amount',
        'collections_amount',
        'previous_collections_amount',
        'source_date',
        'observed_at',
        'calculated_at',
    ];

    /**
     * Échéance des 48 heures du plan correctif, comptée depuis le passage effectif en orange
     * (AC 11). Elle n'a de sens qu'en orange.
     */
    public function correctionPlanDueAt(): ?CarbonImmutable
    {
        if ($this->level !== AlertLevel::Orange) {
            return null;
        }

        return $this->observed_at->addHours(48);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'month' => 'immutable_date',
            'level' => AlertLevel::class,
            'baseline_amount' => 'integer',
            'collections_amount' => 'integer',
            'previous_collections_amount' => 'integer',
            'source_date' => 'immutable_date',
            'observed_at' => 'immutable_datetime',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
