<?php

namespace App\Services\Accountability;

use App\Enums\BlockerState;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Services\Platform\CalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class AccountabilityDashboardService
{
    private const TIMEZONE = 'Africa/Niamey';

    public function __construct(private readonly CalendarService $calendarService) {}

    /** @return array{title: string, status: string, action_label: string, action_url: string, after_approval: bool}|null */
    public function dailyReportBlock(User $user, ?CarbonImmutable $now = null): ?array
    {
        if (! $user->can('rapport_quotidien.creer')) {
            return null;
        }

        $day = ($now ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE)->startOfDay();
        $date = $day->toDateString();

        if (! in_array($date, $this->calendarService->expectedReportDaysFor($user, $day, $day), true)) {
            return null;
        }

        return Cache::remember($this->key($user, $date), now()->addMinutes(5), function () use ($user, $date): array {
            $report = DailyReport::query()
                ->where('author_id', $user->getKey())
                ->whereDate('report_date', $date)
                ->first();

            return [
                'title' => 'Mon rapport du jour',
                'status' => $report?->state->label() ?? 'À préparer',
                'action_label' => $report instanceof DailyReport ? 'Ouvrir mon rapport' : 'Préparer mon rapport',
                'action_url' => route('daily-reports.today', absolute: false),
                'after_approval' => $user->hasRole('direction'),
            ];
        });
    }

    public function invalidate(User $user, ?CarbonImmutable $day = null): void
    {
        $date = ($day ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE)->toDateString();
        Cache::forget($this->key($user, $date));
    }

    /** @return array{title: string, items: list<array{id: int, problem: string, state: string}>, empty_message: string, url: string}|null */
    public function openBlockers(User $user): ?array
    {
        if (! $user->can('blocage.consulter')) {
            return null;
        }

        return Cache::remember("dashboard:accountability:blockers:{$user->getKey()}", now()->addMinutes(5), function () use ($user): array {
            $items = Blocker::query()->visibleTo($user)
                ->whereIn('state', [BlockerState::Ouvert, BlockerState::PrisEnCharge])
                ->oldest()
                ->limit(5)
                ->get(['id', 'problem', 'state'])
                ->map(fn (Blocker $blocker): array => ['id' => (int) $blocker->getKey(), 'problem' => $blocker->problem, 'state' => $blocker->state->value])
                ->all();

            return ['title' => 'Blocages ouverts', 'items' => $items, 'empty_message' => 'Aucun blocage ouvert.', 'url' => route('blockers.index', absolute: false)];
        });
    }

    public function invalidateBlockers(User $user): void
    {
        Cache::forget("dashboard:accountability:blockers:{$user->getKey()}");
    }

    private function key(User $user, string $date): string
    {
        return "dashboard:accountability:daily-report:{$user->getKey()}:{$date}";
    }
}
