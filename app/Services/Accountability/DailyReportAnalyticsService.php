<?php

namespace App\Services\Accountability;

use App\Enums\UserState;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Services\Platform\CalendarService;
use App\Services\Platform\SettingsService;
use App\Support\DateTimeFormatter;
use App\Support\PunctualityCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class DailyReportAnalyticsService
{
    private const TIMEZONE = 'Africa/Niamey';

    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly SettingsService $settingsService,
        private readonly PunctualityCalculator $calculator,
    ) {}

    /** @return array<string, mixed> */
    public function view(User $actor, string $view, CarbonImmutable $anchor, int $page = 1): array
    {
        [$from, $to] = $this->period($view, $anchor);
        $users = $this->visibleUsers($actor, $from, $to);
        $facts = DailyReport::query()
            ->visibleTo($actor)
            ->whereDate('report_date', '>=', $from->toDateString())
            ->whereDate('report_date', '<=', $to->toDateString())
            ->get(['id', 'author_id', 'report_date', 'state', 'submitted_at']);
        $reportPage = DailyReport::query()
            ->visibleTo($actor)
            ->whereDate('report_date', '>=', $from->toDateString())
            ->whereDate('report_date', '<=', $to->toDateString())
            ->with(['author.person', 'currentVersion'])
            ->orderBy('report_date')
            ->orderBy(User::query()
                ->select('people.full_name')
                ->join('people', 'people.id', '=', 'users.person_id')
                ->whereColumn('users.id', 'daily_reports.author_id'))
            ->paginate(perPage: 25, page: $page);
        $reportsByIdentity = $facts->keyBy(
            fn (DailyReport $report): string => $report->author_id.':'.$report->report_date->toDateString(),
        );
        $expected = 0;
        $onTime = 0;
        $missing = [];

        foreach ($users as $user) {
            foreach ($this->expectedDays($user, $from, $to) as $date) {
                $expected++;
                $report = $reportsByIdentity->get($user->getKey().':'.$date);
                if ($report instanceof DailyReport && $this->isOnTime($report)) {
                    $onTime++;
                }
                if ($view === 'quotidienne' && (! $report instanceof DailyReport || ! $report->state->isSubmitted())) {
                    $missing[] = ['user_id' => $user->getKey(), 'name' => $user->person->full_name];
                }
            }
        }

        usort($missing, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return [
            'view' => $view,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'reports' => $reportPage->getCollection()->map(fn (DailyReport $report): array => [
                'id' => $report->getKey(),
                'author' => $report->author->person->full_name,
                'date' => $report->report_date->format('d/m/Y'),
                'state' => $report->state->value,
                'state_label' => $report->state->label(),
                'submitted_at' => $report->submitted_at ? DateTimeFormatter::format($report->submitted_at) : null,
                'result' => $report->currentVersion?->achieved_result,
            ])->all(),
            'pagination' => [
                'current_page' => $reportPage->currentPage(),
                'last_page' => $reportPage->lastPage(),
                'previous_url' => $reportPage->previousPageUrl(),
                'next_url' => $reportPage->nextPageUrl(),
            ],
            'missing' => $missing,
            'punctuality' => [
                'on_time' => $onTime,
                'expected' => $expected,
                'percentage' => $this->calculator->percentage($onTime, $expected),
            ],
        ];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function period(string $view, CarbonImmutable $anchor): array
    {
        $day = $anchor->setTimezone(self::TIMEZONE)->startOfDay();

        return match ($view) {
            'hebdomadaire' => [$day->startOfWeek(), $day->endOfWeek()->startOfDay()],
            'mensuelle' => [$day->startOfMonth(), $day->endOfMonth()->startOfDay()],
            default => [$day, $day],
        };
    }

    /** @return Collection<int, User> */
    private function visibleUsers(User $actor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return User::permission('rapport_quotidien.creer')
            ->where('state', UserState::Actif)
            ->when(! $actor->hasRole('direction'), function (Builder $query) use ($actor): void {
                $query->where(function (Builder $scope) use ($actor): void {
                    $scope->whereKey($actor->getKey())->orWhere('manager_id', $actor->getKey());
                });
            })
            ->where(function (Builder $query) use ($to): void {
                $query->whereNull('contract_start_date')->orWhereDate('contract_start_date', '<=', $to->toDateString());
            })
            ->where(function (Builder $query) use ($from): void {
                $query->whereNull('contract_end_date')->orWhereDate('contract_end_date', '>=', $from->toDateString());
            })
            ->with('person')
            ->orderBy(Person::query()->select('full_name')->whereColumn('people.id', 'users.person_id'))
            ->get();
    }

    /** @return list<string> */
    private function expectedDays(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(array_filter(
            $this->calendarService->expectedReportDaysFor($user, $from, $to),
            static fn (string $date): bool => ($user->contract_start_date === null || $date >= $user->contract_start_date->toDateString())
                && ($user->contract_end_date === null || $date <= $user->contract_end_date->toDateString()),
        ));
    }

    private function isOnTime(DailyReport $report): bool
    {
        // La ponctualité se lit sur l'heure d'envoi, jamais sur l'état : une validation, un retour
        // ou une correction ultérieure ne change pas le fait que le rapport a été envoyé à l'heure.
        if (! $report->submitted_at instanceof CarbonImmutable) {
            return false;
        }

        $deadline = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i',
            $report->report_date->toDateString().' '.$this->settingsService->reportDeadlineTime(),
            self::TIMEZONE,
        )->utc();

        return $report->submitted_at->lessThanOrEqualTo($deadline);
    }
}
