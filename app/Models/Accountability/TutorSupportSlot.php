<?php

namespace App\Models\Accountability;

use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Accountability\TutorSupportSlotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Créneau de suivi paramétré par un tuteur (AC 34). Le jour et l'heure sont exprimés
 * en heure civile de Niamey ; la conversion en UTC est faite au calcul du prochain créneau.
 *
 * @property int $tutor_id
 * @property int $weekday 1 = lundi … 7 = dimanche
 * @property string $start_time
 */
class TutorSupportSlot extends Model
{
    /** @use HasFactory<TutorSupportSlotFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['tutor_id', 'weekday', 'start_time'];

    /** @return BelongsTo<User, $this> */
    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    /**
     * @param  Builder<TutorSupportSlot>  $query
     * @return Builder<TutorSupportSlot>
     */
    public function scopeForTutor(Builder $query, User $tutor): Builder
    {
        return $query->where('tutor_id', $tutor->getKey())->orderBy('weekday')->orderBy('start_time');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }
}
