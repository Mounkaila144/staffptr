<?php

namespace App\Services\Identity;

use App\Models\Identity\LoginAttempt;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class LoginHistoryService
{
    /**
     * @param  array{person_id?: int|string|null, from?: string|null, to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $personId = isset($filters['person_id']) && $filters['person_id'] !== ''
            ? (int) $filters['person_id']
            : null;
        $hasPeriodFilter = ! empty($filters['from']) || ! empty($filters['to']);
        $from = isset($filters['from']) && $filters['from'] !== ''
            ? CarbonImmutable::parse($filters['from'], (string) config('app.display_timezone'))->startOfDay()->utc()
            : ($hasPeriodFilter ? null : CarbonImmutable::now('UTC')->subDays(30));
        $to = isset($filters['to']) && $filters['to'] !== ''
            ? CarbonImmutable::parse($filters['to'], (string) config('app.display_timezone'))->endOfDay()->utc()
            : null;

        $attempts = LoginAttempt::query()
            ->with('user.person')
            ->when($from !== null, static fn ($query) => $query->where('occurred_at', '>=', $from))
            ->when($to !== null, static fn ($query) => $query->where('occurred_at', '<=', $to))
            ->when($personId !== null, static function ($query) use ($personId): void {
                $query->whereHas('user', static fn ($users) => $users->where('person_id', $personId));
            })
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return [
            'attempts' => $this->attemptsForDisplay($attempts),
            'sessions' => $this->openSessions(),
            'people' => Person::query()
                ->orderBy('full_name')
                ->get(['id', 'full_name'])
                ->map(static fn (Person $person): array => [
                    'id' => $person->getKey(),
                    'name' => $person->full_name,
                ])
                ->all(),
            'filters' => [
                'person_id' => $personId,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'filtersActive' => $personId !== null
                || ! empty($filters['from'])
                || ! empty($filters['to']),
            'hasFailedAttemptsLast30Days' => LoginAttempt::query()
                ->where('successful', false)
                ->where('occurred_at', '>=', CarbonImmutable::now('UTC')->subDays(30))
                ->exists(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, LoginAttempt>  $attempts
     * @return array<string, mixed>
     */
    private function attemptsForDisplay(LengthAwarePaginator $attempts): array
    {
        return $attempts->through(static fn (LoginAttempt $attempt): array => [
            'id' => $attempt->getKey(),
            'person' => $attempt->user?->person->full_name ?? 'Compte inconnu',
            'device' => $attempt->user_agent ?: 'Appareil non renseigné',
            'address' => $attempt->ip_address,
            'occurred_at' => DateTimeFormatter::format($attempt->occurred_at),
            'successful' => $attempt->successful,
            'result' => $attempt->successful ? 'Réussie' : 'Échouée',
        ])->toArray();
    }

    /** @return list<array<string, int|string>> */
    private function openSessions(): array
    {
        // Laravel ne fournit pas de modèle Eloquent pour sa table d'infrastructure `sessions`.
        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->orderByDesc('last_activity')
            ->limit(100)
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);
        $users = User::query()
            ->with('person')
            ->whereKey($sessions->pluck('user_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        return $sessions->map(static function (object $session) use ($users): array {
            $user = $users->get((int) $session->user_id);

            return [
                'id' => (string) $session->id,
                'person' => $user?->person->full_name ?? 'Compte inconnu',
                'device' => $session->user_agent ?: 'Appareil non renseigné',
                'address' => $session->ip_address ?: 'Adresse non renseignée',
                'last_activity' => DateTimeFormatter::format(
                    CarbonImmutable::createFromTimestampUTC((int) $session->last_activity),
                ),
            ];
        })->all();
    }
}
