<?php

namespace App\Models\Finance;

use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Finance\FixedChargeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $label
 * @property int $monthly_amount
 * @property bool $is_active
 */
class FixedCharge extends Model
{
    /** @use HasFactory<FixedChargeFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['label', 'monthly_amount', 'is_active'];

    /**
     * @param  Builder<FixedCharge>  $query
     * @return Builder<FixedCharge>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
