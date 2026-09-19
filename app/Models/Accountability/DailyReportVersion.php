<?php

namespace App\Models\Accountability;

use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Accountability\DailyReportVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use LogicException;

class DailyReportVersion extends Model
{
    /** @use HasFactory<DailyReportVersionFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'daily_report_id', 'version_number', 'author_id', 'idempotency_key', 'planned_task', 'achieved_result',
        'evidence_link', 'blocker_present', 'blocker_details', 'next_action', 'help_requested',
        'help_details', 'correction_reason',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Une version de rapport envoyée est immuable.');
        });
    }

    /** @return BelongsTo<DailyReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'daily_report_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return MorphOne<Attachment, $this> */
    public function attachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'blocker_present' => 'boolean',
            'help_requested' => 'boolean',
        ];
    }
}
