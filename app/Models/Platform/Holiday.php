<?php

namespace App\Models\Platform;

use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Platform\HolidayFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $label
 * @property CarbonImmutable $date
 * @property bool $is_active
 */
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['label', 'date', 'is_active'];

    /** @return Attribute<CarbonImmutable, DateTimeInterface|string> */
    protected function date(): Attribute
    {
        return Attribute::make(
            set: static fn (DateTimeInterface|string $value): string => $value instanceof DateTimeInterface
                ? $value->format('Y-m-d')
                : CarbonImmutable::parse($value, 'Africa/Niamey')->format('Y-m-d'),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }
}
