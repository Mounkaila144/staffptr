<?php

namespace App\Models\Accountability;

use App\Enums\InternshipChecklistType;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\InternshipChecklistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $internship_id
 * @property InternshipChecklistType $checklist_type
 * @property int $position
 * @property string $label
 * @property int|null $completed_by
 * @property CarbonImmutable|null $completed_at
 */
class InternshipChecklistItem extends Model
{
    /** @use HasFactory<InternshipChecklistItemFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'internship_id', 'checklist_type', 'position', 'label', 'completed_by', 'completed_at',
    ];

    /** @return BelongsTo<Internship, $this> */
    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'checklist_type' => InternshipChecklistType::class,
            'completed_at' => 'immutable_datetime',
        ];
    }
}
