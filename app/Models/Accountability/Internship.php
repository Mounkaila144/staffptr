<?php

namespace App\Models\Accountability;

use App\Enums\InternshipState;
use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\InternshipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $user_id
 * @property int $internship_intake_form_id
 * @property int $tutor_id
 * @property InternshipState $state
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property CarbonImmutable|null $ended_at
 */
class Internship extends Model
{
    /** @use HasFactory<InternshipFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'internship_intake_form_id', 'tutor_id', 'state', 'start_date', 'end_date', 'ended_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function intern(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    /** @return BelongsTo<InternshipIntakeForm, $this> */
    public function intakeForm(): BelongsTo
    {
        return $this->belongsTo(InternshipIntakeForm::class, 'internship_intake_form_id');
    }

    /** @return HasOne<InternshipPlan, $this> */
    public function plan(): HasOne
    {
        return $this->hasOne(InternshipPlan::class);
    }

    /** @return HasMany<InternshipEvaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(InternshipEvaluation::class);
    }

    /** @return HasMany<InternshipChecklistItem, $this> */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(InternshipChecklistItem::class);
    }

    /**
     * Le stagiaire consulte son dossier, le tuteur ceux de ses stagiaires, `direction` tous ;
     * tout autre accès est refusé, y compris par URL directe (AC 31).
     *
     * @param  Builder<Internship>  $query
     * @return Builder<Internship>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('direction')) {
            return $query;
        }

        return $query->where(fn (Builder $visible): Builder => $visible
            ->where('user_id', $user->getKey())
            ->orWhere('tutor_id', $user->getKey()));
    }

    /**
     * Seuls les stages actifs occupent une place chez le tuteur (AC 23).
     *
     * @param  Builder<Internship>  $query
     * @return Builder<Internship>
     */
    public function scopeOccupyingTutorSlot(Builder $query): Builder
    {
        return $query->whereIn('state', InternshipState::occupyingValues());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => InternshipState::class,
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
