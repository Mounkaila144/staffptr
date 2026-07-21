<?php

namespace App\Services\Identity;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Services\Platform\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ContractEndingService
{
    public function __construct(private readonly SettingsService $settingsService) {}

    /** @return Collection<int, User> */
    public function endingWithin(?int $days = null): Collection
    {
        $resolvedDays = $days ?? $this->settingsService->contractEndWarningDays();

        if ($resolvedDays < 0) {
            throw new InvalidArgumentException('Le délai de fin de contrat doit être un entier positif ou nul.');
        }

        $today = CarbonImmutable::now((string) config('app.display_timezone', 'Africa/Niamey'))->startOfDay();
        $deadline = $today->addDays($resolvedDays);

        return User::query()
            ->where('state', UserState::Actif)
            ->whereNotNull('contract_end_date')
            ->whereDate('contract_end_date', '>=', $today->toDateString())
            ->whereDate('contract_end_date', '<=', $deadline->toDateString())
            ->with('person')
            ->orderBy('contract_end_date')
            ->orderBy('id')
            ->get();
    }
}
