<?php

namespace App\Models\Identity;

use App\Enums\DocumentType;
use App\Enums\UserState;
use App\Models\Platform\Attachment;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\PersonDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property DocumentType $document_type
 * @property CarbonImmutable|null $archived_at
 * @property string|null $archive_reason
 * @property Person $person
 * @property User $uploadedBy
 * @property Attachment|null $attachment
 */
class PersonDocument extends Model
{
    /** @use HasFactory<PersonDocumentFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'person_id', 'document_type', 'archived_at', 'archive_reason', 'uploaded_by',
    ];

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return MorphOne<Attachment, $this> */
    public function attachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }

    /**
     * @param  Builder<PersonDocument>  $query
     * @return Builder<PersonDocument>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('direction')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->where('person_id', $user->person_id)
                ->orWhereHas('person.users', function (Builder $team) use ($user): void {
                    $team->where('manager_id', $user->getKey())
                        ->where('state', UserState::Actif);
                });
        });
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'person_id' => 'integer',
            'document_type' => DocumentType::class,
            'archived_at' => 'immutable_datetime',
            'uploaded_by' => 'integer',
        ];
    }
}
