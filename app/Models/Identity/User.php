<?php

namespace App\Models\Identity;

use App\Enums\RelationType;
use App\Enums\UserState;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\Internship;
use App\Models\Accountability\TaskRequest;
use App\Models\Platform\Attachment;
use App\Models\Work\Objective;
use App\Models\Work\Task as WorkTask;
use App\Support\Auditing\Auditable;
use App\Support\PhoneNumber;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property UserState $state
 * @property RelationType $relation_type
 * @property int $failed_attempts
 * @property CarbonImmutable|null $locked_until
 * @property Person $person
 * @property Department|null $department
 * @property JobFunction|null $jobFunction
 * @property User|null $manager
 * @property CarbonImmutable|null $contract_start_date
 * @property CarbonImmutable|null $contract_end_date
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'person_id',
        'department_id',
        'job_function_id',
        'manager_id',
        'relation_type',
        'contract_start_date',
        'contract_end_date',
        'phone',
        'password',
        'state',
        'must_change_password',
        'locked_until',
        'failed_attempts',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
    ];

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<JobFunction, $this> */
    public function jobFunction(): BelongsTo
    {
        return $this->belongsTo(JobFunction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** @return HasMany<User, $this> */
    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    /** @return HasMany<Objective, $this> */
    public function objectives(): HasMany
    {
        return $this->hasMany(Objective::class);
    }

    /** @return HasMany<WorkTask, $this> */
    public function workTasks(): HasMany
    {
        return $this->hasMany(WorkTask::class, 'assignee_id');
    }

    /** @return HasMany<UserHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(UserHistory::class);
    }

    /** @return HasMany<LoginAttempt, $this> */
    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function uploadedAttachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'uploaded_by');
    }

    /** @return HasMany<PersonDocument, $this> */
    public function uploadedPersonDocuments(): HasMany
    {
        return $this->hasMany(PersonDocument::class, 'uploaded_by');
    }

    /** @return HasMany<Absence, $this> */
    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    /** @return HasMany<DailyReport, $this> */
    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class, 'author_id');
    }

    /** @return HasMany<TaskRequest, $this> */
    public function taskRequests(): HasMany
    {
        return $this->hasMany(TaskRequest::class, 'requested_by');
    }

    /** @return HasMany<Blocker, $this> */
    public function reportedBlockers(): HasMany
    {
        return $this->hasMany(Blocker::class, 'created_by');
    }

    /** @return HasMany<Blocker, $this> */
    public function solicitedBlockers(): HasMany
    {
        return $this->hasMany(Blocker::class, 'solicited_user_id');
    }

    /** @return HasMany<Absence, $this> */
    public function decidedAbsences(): HasMany
    {
        return $this->hasMany(Absence::class, 'decided_by');
    }

    /**
     * Stages encadrés par ce compte, quel que soit son rôle : la limite s'applique aux associés
     * comme aux employés porteurs du rôle `tuteur` (AC 24).
     *
     * @return HasMany<Internship, $this>
     */
    public function supervisedInternships(): HasMany
    {
        return $this->hasMany(Internship::class, 'tutor_id');
    }

    /**
     * Cette portée constitue le contrat commun des index et exports de comptes.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['super_admin', 'direction'])) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->where('person_id', $user->person_id)
                ->orWhere(function (Builder $team) use ($user): void {
                    $team->where('manager_id', $user->getKey())
                        ->where('state', UserState::Actif);
                });
        });
    }

    /** @return Attribute<string, string> */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => PhoneNumber::normalize($value),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => UserState::class,
            'relation_type' => RelationType::class,
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'locked_until' => 'immutable_datetime',
            'failed_attempts' => 'integer',
            'contract_start_date' => 'immutable_date',
            'contract_end_date' => 'immutable_date',
        ];
    }
}
