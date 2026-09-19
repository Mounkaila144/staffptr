<?php

namespace App\Models\Finance;

use App\Enums\AlertLevel;
use App\Enums\MonthlyReportState;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\MonthlyReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $version
 * @property int $prepared_by
 * @property int|null $controlled_by
 * @property int|null $validated_by
 * @property int|null $previous_id
 * @property MonthlyReportState $state
 * @property array<int, array<string, mixed>> $lines
 * @property CarbonImmutable $month
 * @property AlertLevel|null $alert_level
 * @property MonthClosure|null $closure
 */
class MonthlyReport extends Model
{
    /** @use HasFactory<MonthlyReportFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'month',
        'version',
        'previous_id',
        'state',
        'lines',
        'prepared_by',
        'controlled_by',
        'validated_by',
        'controlled_at',
        'validated_at',
        'alert_level',
        'alert_source_date',
    ];

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<User, $this> */
    public function controller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controlled_by');
    }

    /** @return BelongsTo<User, $this> */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /** @return BelongsTo<MonthlyReport, $this> */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_id');
    }

    /** @return HasOne<MonthClosure, $this> */
    public function closure(): HasOne
    {
        return $this->hasOne(MonthClosure::class);
    }

    /**
     * @param  Builder<MonthlyReport>  $query
     * @return Builder<MonthlyReport>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasAnyRole(['direction', 'finance']) ? $query : $query->whereRaw('1 = 0');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'month' => 'immutable_date',
            'version' => 'integer',
            'state' => MonthlyReportState::class,
            'lines' => 'array',
            'controlled_at' => 'immutable_datetime',
            'validated_at' => 'immutable_datetime',
            'alert_level' => AlertLevel::class,
            'alert_source_date' => 'immutable_date',
        ];
    }
}
