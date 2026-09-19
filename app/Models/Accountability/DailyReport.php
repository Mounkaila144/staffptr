<?php

namespace App\Models\Accountability;

use App\Enums\DailyReportState;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\DailyReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $author_id
 * @property CarbonImmutable $report_date
 * @property DailyReportState $state
 * @property CarbonImmutable|null $submitted_at
 * @property string|null $lateness_explanation
 * @property User $author
 * @property DailyReportVersion|null $currentVersion
 */
class DailyReport extends Model
{
    /** @use HasFactory<DailyReportFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'author_id',
        'report_date',
        'state',
        'submitted_at',
        'lateness_explanation',
    ];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<DailyReportVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DailyReportVersion::class);
    }

    /** @return HasOne<DailyReportVersion, $this> */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(DailyReportVersion::class)->ofMany('version_number', 'max');
    }

    /** @return HasMany<DailyReportComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(DailyReportComment::class);
    }

    /** @return HasMany<DailyReportDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(DailyReportDecision::class);
    }

    /** @return HasMany<TaskRequest, $this> */
    public function taskRequests(): HasMany
    {
        return $this->hasMany(TaskRequest::class);
    }

    /**
     * @param  Builder<DailyReport>  $query
     * @return Builder<DailyReport>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('direction')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user): void {
            $visible->where('author_id', $user->getKey())
                ->orWhereHas('author', fn (Builder $author): Builder => $author->where('manager_id', $user->getKey()));
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'report_date' => 'immutable_date',
            'state' => DailyReportState::class,
            'submitted_at' => 'immutable_datetime',
        ];
    }
}
