<?php

namespace App\Models\Accountability;

use App\Models\Identity\User;
use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Carbon\CarbonImmutable;
use Database\Factories\Accountability\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Arrivée et départ d'un stagiaire pour une journée, enregistrés depuis le poste de la direction.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $work_date
 * @property CarbonImmutable $arrived_at
 * @property int $arrival_recorded_by
 * @property CarbonImmutable|null $left_at
 * @property int|null $departure_recorded_by
 * @property string $expected_arrival_time
 * @property int $tolerance_minutes
 * @property int $late_minutes
 * @property User $intern
 * @property User $arrivalRecorder
 * @property User|null $departureRecorder
 */
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'work_date',
        'arrived_at',
        'arrival_recorded_by',
        'left_at',
        'departure_recorded_by',
        'expected_arrival_time',
        'tolerance_minutes',
        'late_minutes',
    ];

    /** @return BelongsTo<User, $this> */
    public function intern(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function arrivalRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arrival_recorded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function departureRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'departure_recorded_by');
    }

    public function isLate(): bool
    {
        return $this->late_minutes > 0;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'work_date' => 'immutable_date',
            'arrived_at' => 'immutable_datetime',
            'left_at' => 'immutable_datetime',
            'tolerance_minutes' => 'integer',
            'late_minutes' => 'integer',
        ];
    }
}
