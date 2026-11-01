<?php

namespace App\Services\Accountability;

use App\Enums\InternshipState;
use App\Models\Accountability\AttendanceRecord;
use App\Models\Accountability\Internship;
use App\Models\Identity\User;
use App\Services\Platform\SettingsService;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Arrivées et départs des stagiaires (story 13.1).
 *
 * L'heure est toujours celle du serveur au moment du clic : rien n'est saisi, donc rien ne
 * s'antidate. L'enregistrement est réservé au poste de la direction ; le stagiaire et son tuteur
 * ne font que consulter.
 */
final class AttendanceService
{
    private const TIMEZONE = 'Africa/Niamey';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SettingsService $settings,
    ) {}

    public function recordArrival(User $intern, User $actor, ?CarbonImmutable $at = null): AttendanceRecord
    {
        $at ??= CarbonImmutable::now('UTC');
        $this->assertActiveIntern($intern);
        $workDate = $at->setTimezone(self::TIMEZONE)->toDateString();

        return DB::connection((new AttendanceRecord)->getConnectionName())->transaction(function () use ($intern, $actor, $at, $workDate): AttendanceRecord {
            $existing = AttendanceRecord::query()->where('user_id', $intern->getKey())->whereDate('work_date', $workDate)->lockForUpdate()->first();
            if ($existing instanceof AttendanceRecord) {
                throw ValidationException::withMessages(['attendance' => "L’arrivée de {$this->name($intern)} est déjà enregistrée à {$this->clock($existing->arrived_at)}."]);
            }

            $expected = $this->settings->internArrivalTime();
            $tolerance = $this->settings->internArrivalToleranceMinutes();
            $record = new AttendanceRecord([
                'user_id' => $intern->getKey(),
                'work_date' => $workDate,
                'arrived_at' => $at,
                'arrival_recorded_by' => $actor->getKey(),
                'expected_arrival_time' => $expected,
                'tolerance_minutes' => $tolerance,
                'late_minutes' => $this->lateMinutes($at, $workDate, $expected, $tolerance),
            ]);
            $this->auditLogger->runExplicitly($record, fn (): bool => $record->saveOrFail(), $actor->getKey(), $this->name($actor), 'attendance_arrival_recorded', newValues: [
                'user_id' => $intern->getKey(),
                'work_date' => $workDate,
                'arrived_at' => $at->toIso8601String(),
                'late_minutes' => $record->late_minutes,
            ]);

            return $record;
        });
    }

    public function recordDeparture(User $intern, User $actor, ?CarbonImmutable $at = null): AttendanceRecord
    {
        $at ??= CarbonImmutable::now('UTC');
        $workDate = $at->setTimezone(self::TIMEZONE)->toDateString();

        return DB::connection((new AttendanceRecord)->getConnectionName())->transaction(function () use ($intern, $actor, $at, $workDate): AttendanceRecord {
            $record = AttendanceRecord::query()->where('user_id', $intern->getKey())->whereDate('work_date', $workDate)->lockForUpdate()->first();
            if (! $record instanceof AttendanceRecord) {
                throw ValidationException::withMessages(['attendance' => "Enregistrez d’abord l’arrivée de {$this->name($intern)} : aucun départ sans arrivée."]);
            }
            if ($record->left_at !== null) {
                throw ValidationException::withMessages(['attendance' => "Le départ de {$this->name($intern)} est déjà enregistré à {$this->clock($record->left_at)}."]);
            }

            $record->left_at = $at;
            $record->departure_recorded_by = (int) $actor->getKey();
            $this->auditLogger->runExplicitly($record, fn (): bool => $record->saveOrFail(), $actor->getKey(), $this->name($actor), 'attendance_departure_recorded', ['left_at' => null], ['left_at' => $at->toIso8601String()]);

            return $record;
        });
    }

    /**
     * Stagiaires en stage actif visibles de l'acteur, avec leur ligne du jour s'il y en a une.
     *
     * @return list<array{intern: User, record: AttendanceRecord|null}>
     */
    public function register(User $viewer, CarbonImmutable $day): array
    {
        $internships = Internship::query()->visibleTo($viewer)->where('state', InternshipState::Actif->value)->with('intern.person')->get();
        $records = AttendanceRecord::query()->whereIn('user_id', $internships->pluck('user_id'))->whereDate('work_date', $day->toDateString())->get()->keyBy('user_id');

        $rows = [];
        foreach ($internships as $internship) {
            $intern = $internship->intern;
            if (isset($rows[$intern->getKey()])) {
                continue;
            }
            $rows[$intern->getKey()] = ['intern' => $intern, 'record' => $records->get($intern->getKey())];
        }
        usort($rows, fn (array $left, array $right): int => strcmp($this->name($left['intern']), $this->name($right['intern'])));

        return $rows;
    }

    /**
     * Historique des lignes visibles de l'acteur, du plus récent au plus ancien.
     *
     * @return Collection<int, AttendanceRecord>
     */
    public function history(User $viewer, int $limit = 60): Collection
    {
        $visibleInterns = Internship::query()->visibleTo($viewer)->pluck('user_id');

        return AttendanceRecord::query()->with(['intern.person', 'arrivalRecorder.person', 'departureRecorder.person'])
            ->whereIn('user_id', $visibleInterns)->orderByDesc('work_date')->orderByDesc('arrived_at')->limit($limit)->get();
    }

    /**
     * Minutes écoulées depuis l'heure attendue, comptées seulement au-delà de la tolérance : une
     * arrivée à 8 h 07 avec dix minutes de tolérance n'est pas un retard, une arrivée à 8 h 25
     * est un retard de vingt-cinq minutes, mesuré depuis 8 h.
     */
    private function lateMinutes(CarbonImmutable $at, string $workDate, string $expected, int $tolerance): int
    {
        $expectedAt = CarbonImmutable::parse("{$workDate} {$expected}", self::TIMEZONE);
        $minutes = (int) floor(($at->getTimestamp() - $expectedAt->getTimestamp()) / 60);

        return $minutes > $tolerance ? $minutes : 0;
    }

    private function assertActiveIntern(User $intern): void
    {
        $active = Internship::query()->where('user_id', $intern->getKey())->where('state', InternshipState::Actif->value)->exists();
        if (! $active) {
            throw ValidationException::withMessages(['attendance' => "{$this->name($intern)} n’a pas de stage actif : aucune arrivée ne peut être enregistrée."]);
        }
    }

    private function clock(CarbonImmutable $at): string
    {
        return $at->setTimezone(self::TIMEZONE)->format('H\hi');
    }

    private function name(User $user): string
    {
        return $user->person()->value('full_name') ?? "Compte #{$user->getKey()}";
    }
}
