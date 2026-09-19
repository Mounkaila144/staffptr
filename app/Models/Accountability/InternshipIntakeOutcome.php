<?php

namespace App\Models\Accountability;

use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Accountability\InternshipIntakeOutcomeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Résultat attendu d'une fiche d'entrée : trois au minimum sont exigés (AC 14).
 *
 * @property int $internship_intake_form_id
 * @property int $position
 * @property string $description
 */
class InternshipIntakeOutcome extends Model
{
    /** @use HasFactory<InternshipIntakeOutcomeFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['internship_intake_form_id', 'position', 'description'];

    /** @return BelongsTo<InternshipIntakeForm, $this> */
    public function intakeForm(): BelongsTo
    {
        return $this->belongsTo(InternshipIntakeForm::class, 'internship_intake_form_id');
    }
}
