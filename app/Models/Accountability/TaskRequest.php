<?php

namespace App\Models\Accountability;

use App\Enums\TaskRequestState;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Accountability\TaskRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property TaskRequestState $state
 */
class TaskRequest extends Model
{
    /** @use HasFactory<TaskRequestFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'daily_report_id', 'requested_by', 'responsible_id', 'description', 'is_urgent',
        'state', 'treated_by', 'treated_at',
    ];

    /** @return BelongsTo<DailyReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'daily_report_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /** @return BelongsTo<User, $this> */
    public function treatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'treated_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_urgent' => 'boolean',
            'state' => TaskRequestState::class,
            'treated_at' => 'immutable_datetime',
        ];
    }
}
