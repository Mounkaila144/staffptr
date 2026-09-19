<?php

namespace App\Models\Finance;

use App\Enums\AlertLevel;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Finance\MonthClosureFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $version
 * @property CarbonImmutable $month
 * @property CarbonImmutable|null $reopened_at
 * @property AlertLevel|null $frozen_alert_level
 */
class MonthClosure extends Model
{
    /** @use HasFactory<MonthClosureFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'month',
        'version',
        'monthly_report_id',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
        'frozen_alert_level',
    ];

    /** @return BelongsTo<MonthlyReport, $this> */
    public function monthlyReport(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * @param  Builder<MonthClosure>  $query
     * @return Builder<MonthClosure>
     */
    public function scopeCurrentlyClosed(Builder $query): Builder
    {
        return $query->whereNull('reopened_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'month' => 'immutable_date',
            'version' => 'integer',
            'closed_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
            'frozen_alert_level' => AlertLevel::class,
        ];
    }
}
