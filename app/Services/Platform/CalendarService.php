<?php

namespace App\Services\Platform;

use App\Enums\AbsenceState;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Models\Platform\Holiday;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class CalendarService
{
    private const TIMEZONE = 'Africa/Niamey';

    /** @var array<int, string> */
    private const DAY_CODES = [
        1 => 'lun',
        2 => 'mar',
        3 => 'mer',
        4 => 'jeu',
        5 => 'ven',
        6 => 'sam',
        7 => 'dim',
    ];

    public function __construct(private readonly SettingsService $settingsService) {}

    public function isWorkingDay(CarbonImmutable|string $date): bool
    {
        return $this->period($date, $date)[0]['is_working_day'];
    }

    /** @return list<string> */
    public function expectedWorkingDays(CarbonImmutable|string $from, CarbonImmutable|string $to): array
    {
        return array_values(array_map(
            static fn (array $day): string => $day['date'],
            array_filter($this->period($from, $to), static fn (array $day): bool => $day['is_working_day']),
        ));
    }

    /** @return list<string> */
    public function nonWorkingDays(CarbonImmutable|string $from, CarbonImmutable|string $to): array
    {
        return array_values(array_map(
            static fn (array $day): string => $day['date'],
            array_filter($this->period($from, $to), static fn (array $day): bool => ! $day['is_working_day']),
        ));
    }

    /** @return list<string> */
    public function expectedReportDaysFor(User $user, CarbonImmutable|string $from, CarbonImmutable|string $to): array
    {
        $start = $this->civilDate($from);
        $end = $this->civilDate($to);
        $expectedDays = $this->expectedWorkingDays($start, $end);
        $approvedAbsenceDays = [];

        $absences = Absence::query()
            ->where('user_id', $user->getKey())
            ->where('state', AbsenceState::Approuvee)
            ->overlapping($start->format('Y-m-d'), $end->format('Y-m-d'))
            ->get(['start_date', 'end_date']);

        foreach ($absences as $absence) {
            $civilAbsenceStart = $this->civilDate($absence->start_date->format('Y-m-d'));
            $civilAbsenceEnd = $this->civilDate($absence->end_date->format('Y-m-d'));
            $absenceStart = $civilAbsenceStart->greaterThan($start) ? $civilAbsenceStart : $start;
            $absenceEnd = $civilAbsenceEnd->lessThan($end) ? $civilAbsenceEnd : $end;

            for ($date = $absenceStart; $date->lessThanOrEqualTo($absenceEnd); $date = $date->addDay()) {
                $approvedAbsenceDays[$date->format('Y-m-d')] = true;
            }
        }

        return array_values(array_filter(
            $expectedDays,
            static fn (string $date): bool => ! isset($approvedAbsenceDays[$date]),
        ));
    }

    /**
     * @return list<array{date: string, day_number: int, weekday: string, weekday_label: string, is_working_day: bool, status_label: string|null}>
     */
    public function period(CarbonImmutable|string $from, CarbonImmutable|string $to): array
    {
        $start = $this->civilDate($from);
        $end = $this->civilDate($to);

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('La date de début doit précéder la date de fin.');
        }

        $holidays = Holiday::query()
            ->where('is_active', true)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get(['date', 'label'])
            ->keyBy(static fn (Holiday $holiday): string => $holiday->date->format('Y-m-d'));
        $workingDays = $this->settingsService->workingDays();
        $result = [];

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            $holiday = $holidays->get($dateKey);
            $weekday = self::DAY_CODES[$date->dayOfWeekIso];
            $isWorkingDay = in_array($weekday, $workingDays, true) && ! $holiday instanceof Holiday;

            $result[] = [
                'date' => $dateKey,
                'day_number' => $date->day,
                'weekday' => $weekday,
                'weekday_label' => $date->locale('fr')->isoFormat('dddd'),
                'is_working_day' => $isWorkingDay,
                'status_label' => $isWorkingDay ? null : $this->statusLabel($date, $holiday),
            ];
        }

        return $result;
    }

    private function civilDate(CarbonImmutable|string $date): CarbonImmutable
    {
        $value = $date instanceof CarbonImmutable ? $date->format('Y-m-d') : $date;
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $value, self::TIMEZONE);

        if (! $parsed instanceof CarbonImmutable || $parsed->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("La date {$value} est invalide.");
        }

        return $parsed;
    }

    private function statusLabel(CarbonImmutable $date, mixed $holiday): string
    {
        if ($holiday instanceof Holiday) {
            return "Férié : {$holiday->label}";
        }

        return $date->isWeekend() ? 'Week-end' : 'Fermé';
    }
}
