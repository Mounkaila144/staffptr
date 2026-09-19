<?php

namespace App\Models\Accountability;

use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Accountability\SupportRequestBatchItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une demande dans un envoi groupé. L'unicité de `blocker_id` garantit qu'aucune demande
 * n'est perdue, dupliquée ni fusionnée (AC 38).
 *
 * @property int $support_request_batch_id
 * @property int $blocker_id
 */
class SupportRequestBatchItem extends Model
{
    /** @use HasFactory<SupportRequestBatchItemFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['support_request_batch_id', 'blocker_id'];

    /** @return BelongsTo<SupportRequestBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(SupportRequestBatch::class, 'support_request_batch_id');
    }

    /** @return BelongsTo<Blocker, $this> */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Blocker::class);
    }
}
