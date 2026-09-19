<?php

namespace App\Models\Work;

use App\Enums\DeliverableStatus;
use App\Models\Identity\User;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Work\DeliverableStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $deliverable_id
 * @property DeliverableStatus|null $from_status
 * @property DeliverableStatus $to_status
 * @property int $actor_id
 * @property string $reason
 * @property CarbonImmutable $changed_at
 */
class DeliverableStatusHistory extends Model
{
    /** @use HasFactory<DeliverableStatusHistoryFactory> */
    use HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['deliverable_id', 'from_status', 'to_status', 'actor_id', 'reason', 'changed_at'];

    /** @return BelongsTo<Deliverable, $this> */
    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_status' => DeliverableStatus::class, 'to_status' => DeliverableStatus::class, 'changed_at' => 'immutable_datetime'];
    }
}
