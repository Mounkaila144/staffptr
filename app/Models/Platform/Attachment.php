<?php

namespace App\Models\Platform;

use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Platform\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $ulid
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property string|null $thumbnail_path
 * @property Model $attachable
 * @property User $uploadedBy
 */
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'attachable_type', 'attachable_id', 'disk', 'path', 'original_name',
        'mime_type', 'extension', 'size_bytes', 'thumbnail_path', 'uploaded_by',
    ];

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attachable_id' => 'integer',
            'size_bytes' => 'integer',
            'uploaded_by' => 'integer',
        ];
    }
}
