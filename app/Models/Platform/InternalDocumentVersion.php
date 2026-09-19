<?php

namespace App\Models\Platform;

use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\Auditing\Immutable;
use Carbon\CarbonImmutable;
use Database\Factories\Platform\InternalDocumentVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property int $internal_document_id
 * @property int $version_number
 * @property string|null $body
 * @property CarbonImmutable $effective_date
 * @property int $published_by
 * @property CarbonImmutable $published_at
 * @property InternalDocument $document
 * @property User $publisher
 * @property Attachment|null $attachment
 */
class InternalDocumentVersion extends Model
{
    /** @use HasFactory<InternalDocumentVersionFactory> */
    use Auditable, HasFactory, Immutable;

    /** @var list<string> */
    protected $fillable = [
        'internal_document_id',
        'version_number',
        'body',
        'effective_date',
        'published_by',
        'published_at',
    ];

    /** @return BelongsTo<InternalDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(InternalDocument::class, 'internal_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<InternalDocumentAcknowledgement, $this> */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(InternalDocumentAcknowledgement::class);
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
            'effective_date' => 'immutable_date',
            'published_by' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }
}
