<?php

namespace App\Models\Accountability;

use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Accountability\InternshipPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan de stage : compétences à apprendre, objectifs, tâches hebdomadaires,
 * preuves attendues (AC 27).
 *
 * @property int $internship_id
 * @property string $skills_to_learn
 * @property string $objectives
 * @property string $weekly_tasks
 * @property string $expected_evidence
 */
class InternshipPlan extends Model
{
    /** @use HasFactory<InternshipPlanFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'internship_id', 'skills_to_learn', 'objectives', 'weekly_tasks', 'expected_evidence',
    ];

    /** @return BelongsTo<Internship, $this> */
    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }
}
