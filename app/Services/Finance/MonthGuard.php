<?php

namespace App\Services\Finance;

use App\Models\Finance\MonthClosure;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

final class MonthGuard
{
    /** @var array<int, string> */
    private const MONTHS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    public function assertOpen(DateTimeInterface|string $date): void
    {
        $month = CarbonImmutable::parse($date, 'Africa/Niamey')->startOfMonth();
        if (MonthClosure::query()->currentlyClosed()->whereDate('month', $month->toDateString())->exists()) {
            $label = self::MONTHS[(int) $month->format('n')].' '.$month->format('Y');
            throw ValidationException::withMessages([
                'received_at' => "Le mois de {$label} est clôturé. Aucune écriture ne peut y être imputée.",
            ]);
        }
    }

    public function recordedAfterReopen(DateTimeInterface|string $date): bool
    {
        $month = CarbonImmutable::parse($date, 'Africa/Niamey')->startOfMonth();

        return MonthClosure::query()
            ->whereDate('month', $month->toDateString())
            ->whereNotNull('reopened_at')
            ->exists();
    }
}
