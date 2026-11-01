<?php

namespace App\Http\Controllers\Accountability;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\RecordAttendanceRequest;
use App\Models\Accountability\AttendanceRecord;
use App\Models\Identity\User;
use App\Services\Accountability\AttendanceService;
use App\Services\Platform\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    private const TIMEZONE = 'Africa/Niamey';

    public function __construct(private readonly AttendanceService $attendance, private readonly SettingsService $settings) {}

    public function index(Request $request): Response
    {
        $viewer = $this->actor($request);
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        return Inertia::render('Accountability/Attendance/Index', [
            'today_label' => $today->locale('fr')->isoFormat('dddd D MMMM YYYY'),
            'expected_label' => sprintf('Arrivée attendue à %s, tolérance de %d minutes.', str_replace(':', ' h ', $this->settings->internArrivalTime()), $this->settings->internArrivalToleranceMinutes()),
            'can_record' => $viewer->can('arrivee_depart.enregistrer'),
            'register' => collect($this->attendance->register($viewer, $today))->map(fn (array $row): array => [
                'intern_id' => (int) $row['intern']->getKey(),
                'name' => $row['intern']->person->full_name,
                'record' => $row['record'] instanceof AttendanceRecord ? $this->serialize($row['record']) : null,
            ])->values()->all(),
            'history' => $this->attendance->history($viewer)->map(fn (AttendanceRecord $record): array => [
                ...$this->serialize($record),
                'name' => $record->intern->person->full_name,
                'date_label' => $record->work_date->format('d/m/Y'),
                'recorded_by' => $record->arrivalRecorder->person->full_name,
            ])->values()->all(),
        ]);
    }

    public function arrival(RecordAttendanceRequest $request, User $user): RedirectResponse
    {
        $record = $this->attendance->recordArrival($user, $this->actor($request));
        $label = $record->isLate() ? ' — r'.mb_substr($this->lateText($record->late_minutes), 1) : '';

        return back()->with('success', sprintf('Arrivée enregistrée à %s%s.', $this->clock($record->arrived_at), $label));
    }

    public function departure(RecordAttendanceRequest $request, User $user): RedirectResponse
    {
        $record = $this->attendance->recordDeparture($user, $this->actor($request));

        return back()->with('success', sprintf('Départ enregistré à %s.', $this->clock($record->left_at ?? CarbonImmutable::now('UTC'))));
    }

    /** @return array<string, mixed> */
    private function serialize(AttendanceRecord $record): array
    {
        return [
            'id' => (int) $record->getKey(),
            'arrived_at_label' => $this->clock($record->arrived_at),
            'left_at_label' => $record->left_at ? $this->clock($record->left_at) : null,
            'late_minutes' => $record->late_minutes,
            // Le retard est dit en toutes lettres : la couleur n'en est jamais le seul signe.
            'late_label' => $record->isLate() ? $this->lateText($record->late_minutes) : 'À l’heure',
            'duration_label' => $record->left_at ? $this->duration($record->arrived_at, $record->left_at) : null,
        ];
    }

    /**
     * « Retard de 25 min », ou « Retard de 7 h 22 min » au-delà d'une heure : « 442 min » ne se
     * lisait pas d'un coup d'œil.
     */
    private function lateText(int $minutes): string
    {
        return $minutes < 60
            ? sprintf('Retard de %d min', $minutes)
            : sprintf('Retard de %d h %02d min', intdiv($minutes, 60), $minutes % 60);
    }

    private function clock(CarbonImmutable $at): string
    {
        return $at->setTimezone(self::TIMEZONE)->format('H\hi');
    }

    private function duration(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $minutes = intdiv(max(0, $to->getTimestamp() - $from->getTimestamp()), 60);

        return sprintf('%d h %02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
