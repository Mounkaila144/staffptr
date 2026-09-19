<?php

namespace App\Models\Platform;

use App\Support\Auditing\Immutable;
use Carbon\CarbonImmutable;
use Database\Factories\Platform\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $actor_id
 * @property string $actor_label
 * @property CarbonImmutable $occurred_at
 * @property string $auditable_type
 * @property int|null $auditable_id
 * @property string $action
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $reason
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, Immutable;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'actor_id',
        'actor_label',
        'occurred_at',
        'auditable_type',
        'auditable_id',
        'action',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
        'request_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'auditable_id' => 'integer',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
