<?php

namespace App\Models\Work;

use App\Enums\ProjectStatus;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Work\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $client_name
 * @property int $manager_id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property ProjectStatus $status
 * @property int|null $planned_budget_xof
 * @property int|null $spent_budget_xof
 * @property User $manager
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['name', 'client_name', 'manager_id', 'start_date', 'end_date', 'status', 'planned_budget_xof', 'spent_budget_xof'];

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Périmètre de visibilité d'un projet.
     *
     * Le projet n'a pas de restriction ligne à ligne : `ProjectPolicy::viewAny()` autorise tout
     * détenteur de `projet.consulter`, et l'écran de liste ne filtre pas au-delà. Ce scope rend la
     * règle **explicite et réutilisable**, pour que la recherche transverse et les exports
     * appliquent exactement la même restriction que l'écran d'origine (PERM-06, story 10.1 AC 2,
     * AC 14) au lieu de la réinventer.
     *
     * Écrire `whereRaw('1 = 0')` plutôt que renvoyer une collection vide est délibéré : le compteur
     * de résultats reste une agrégation SQL et ne peut donc pas trahir l'existence d'un projet
     * caché (AC 3).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->can('projet.consulter') ? $query : $query->whereRaw('1 = 0');
    }

    /** @return HasMany<ProjectMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /** @return HasMany<ProjectStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ProjectStatusHistory::class);
    }

    /** @return HasMany<Objective, $this> */
    public function objectives(): HasMany
    {
        return $this->hasMany(Objective::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<Deliverable, $this> */
    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class);
    }

    /** @return MorphMany<WorkComment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(WorkComment::class, 'commentable');
    }

    /** @return MorphMany<WorkLink, $this> */
    public function links(): MorphMany
    {
        return $this->morphMany(WorkLink::class, 'linkable');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'status' => ProjectStatus::class, 'planned_budget_xof' => 'integer', 'spent_budget_xof' => 'integer'];
    }
}
