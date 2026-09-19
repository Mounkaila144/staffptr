<?php

namespace App\Models\Platform;

use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\Auditing\Immutable;
use Carbon\CarbonImmutable;
use Database\Factories\Platform\InternalDocumentAcknowledgementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $internal_document_version_id
 * @property int $user_id
 * @property CarbonImmutable $acknowledged_at
 * @property InternalDocumentVersion $version
 * @property User $user
 */
class InternalDocumentAcknowledgement extends Model
{
    /** @use HasFactory<InternalDocumentAcknowledgementFactory> */
    use Auditable, HasFactory, Immutable;

    /** @var list<string> */
    protected $fillable = ['internal_document_version_id', 'user_id', 'acknowledged_at'];

    /** @return BelongsTo<InternalDocumentVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(InternalDocumentVersion::class, 'internal_document_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'acknowledged_at' => 'immutable_datetime',
        ];
    }
}
