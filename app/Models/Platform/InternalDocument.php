<?php

namespace App\Models\Platform;

use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Platform\InternalDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $title
 * @property bool $requires_acknowledgement
 * @property int|null $current_version_id
 * @property InternalDocumentVersion|null $currentVersion
 */
class InternalDocument extends Model
{
    /** @use HasFactory<InternalDocumentFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['title', 'requires_acknowledgement', 'current_version_id'];

    /** @return HasMany<InternalDocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(InternalDocumentVersion::class);
    }

    /** @return BelongsTo<InternalDocumentVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(InternalDocumentVersion::class, 'current_version_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requires_acknowledgement' => 'boolean',
            'current_version_id' => 'integer',
        ];
    }
}
