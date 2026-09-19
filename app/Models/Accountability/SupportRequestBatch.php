<?php

namespace App\Models\Accountability;

use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\SupportRequestBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regroupement des demandes non urgentes d'un tuteur, présentées au créneau suivant
 * en une seule notification (AC 35). Chaque demande reste un objet distinct (AC 38).
 *
 * @property int $tutor_id
 * @property CarbonImmutable $scheduled_for
 * @property CarbonImmutable|null $delivered_at
 * @property string $idempotency_key
 */
class SupportRequestBatch extends Model
{
    /** @use HasFactory<SupportRequestBatchFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['tutor_id', 'scheduled_for', 'delivered_at', 'idempotency_key'];

    /** @return BelongsTo<User, $this> */
    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    /** @return HasMany<SupportRequestBatchItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SupportRequestBatchItem::class);
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }
}
