<?php

namespace App\Models\Platform;

use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Platform\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property mixed $value
 * @property string $type
 * @property CarbonImmutable $effective_at
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'key',
        'value',
        'type',
        'effective_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'effective_at' => 'immutable_datetime',
        ];
    }
}
