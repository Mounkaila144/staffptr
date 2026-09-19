<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CalendarIndexRequest;
use App\Http\Requests\Platform\ChangeHolidayActivityRequest;
use App\Http\Requests\Platform\StoreHolidayRequest;
use App\Http\Requests\Platform\UpdateHolidayRequest;
use App\Models\Identity\User;
use App\Models\Platform\Holiday;
use App\Services\Platform\CalendarService;
use App\Services\Platform\HolidayService;
use App\Services\Platform\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /** @var array<string, string> */
    private const DAY_LABELS = [
        'lun' => 'Lundi',
        'mar' => 'Mardi',
        'mer' => 'Mercredi',
        'jeu' => 'Jeudi',
        'ven' => 'Vendredi',
        'sam' => 'Samedi',
        'dim' => 'Dimanche',
    ];

    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly HolidayService $holidayService,
        private readonly SettingsService $settingsService,
    ) {}

    public function index(CalendarIndexRequest $request): Response
    {
        Gate::authorize('viewAny', Holiday::class);
        $validated = $request->validated();
        $today = CarbonImmutable::now('Africa/Niamey');
        $year = (int) ($validated['year'] ?? $today->year);
        $month = (int) ($validated['month'] ?? $today->month);
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Africa/Niamey');
        $end = $start->endOfMonth();
        $previous = $start->subMonth();
        $next = $start->addMonth();

        return Inertia::render('Platform/Calendar/Index', [
            'period' => [
                'year' => $year,
                'month' => $month,
                'label' => ucfirst($start->locale('fr')->isoFormat('MMMM YYYY')),
                'leading_empty_days' => $start->dayOfWeekIso - 1,
                'previous' => ['year' => $previous->year, 'month' => $previous->month],
                'next' => ['year' => $next->year, 'month' => $next->month],
            ],
            'days' => $this->calendarService->period($start, $end),
            'workingDays' => array_map(
                static fn (string $code): array => ['code' => $code, 'label' => self::DAY_LABELS[$code]],
                $this->settingsService->workingDays(),
            ),
            'holidays' => Holiday::query()
                ->whereYear('date', $year)
                ->orderBy('date')
                ->get()
                ->map(static fn (Holiday $holiday): array => [
                    'id' => $holiday->getKey(),
                    'label' => $holiday->label,
                    'date' => $holiday->date->format('Y-m-d'),
                    'display_date' => $holiday->date->locale('fr')->isoFormat('D MMMM YYYY'),
                    'is_active' => $holiday->is_active,
                ]),
        ]);
    }

    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        Gate::authorize('create', Holiday::class);
        $this->holidayService->create($request->validated(), $this->actor($request));

        return redirect()->route('calendar.index', $this->periodQuery($request));
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        Gate::authorize('update', $holiday);
        $this->holidayService->update($holiday, $request->validated(), $this->actor($request));

        return redirect()->route('calendar.index', $this->periodQuery($request));
    }

    public function deactivate(ChangeHolidayActivityRequest $request, Holiday $holiday): RedirectResponse
    {
        Gate::authorize('update', $holiday);
        $this->holidayService->deactivate($holiday, $this->actor($request));

        return redirect()->route('calendar.index', $this->periodQuery($request));
    }

    public function reactivate(ChangeHolidayActivityRequest $request, Holiday $holiday): RedirectResponse
    {
        Gate::authorize('update', $holiday);
        $this->holidayService->reactivate($holiday, $this->actor($request));

        return redirect()->route('calendar.index', $this->periodQuery($request));
    }

    /** @return array{year?: int, month?: int} */
    private function periodQuery(Request $request): array
    {
        $year = $request->integer('calendar_year');
        $month = $request->integer('calendar_month');

        return $year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12
            ? ['year' => $year, 'month' => $month]
            : [];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
