<?php

namespace App\Models\Work;

use App\Models\Identity\User;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Work\ObjectiveVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $objective_id
 * @property int $version_number
 * @property array<string, mixed> $previous_values
 * @property array<string, mixed> $new_values
 * @property string $reason
 * @property int $author_id
 */
class ObjectiveVersion extends Model
{
    /** @use HasFactory<ObjectiveVersionFactory> */
    use HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['objective_id', 'version_number', 'previous_values', 'new_values', 'reason', 'author_id'];

    /** @return BelongsTo<Objective, $this> */
    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['previous_values' => 'array', 'new_values' => 'array', 'version_number' => 'integer'];
    }
}
