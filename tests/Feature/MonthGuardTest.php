<?php

namespace Tests\Feature;

use App\Models\Finance\MonthClosure;
use App\Models\Identity\User;
use App\Services\Finance\MonthGuard;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

class MonthGuardTest extends IdentityTestCase
{
    public function test_ac_33_closed_month_is_named_and_rejected(): void
    {
        MonthClosure::factory()->create(['month' => '2026-06-01', 'reopened_at' => null]);

        try {
            app(MonthGuard::class)->assertOpen('2026-06-18 10:00:00');
            $this->fail('Le mois clos devait être refusé.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Le mois de juin 2026 est clôturé. Aucune écriture ne peut y être imputée.',
                $exception->errors()['received_at'][0],
            );
        }
    }

    public function test_ac_91_reopened_month_is_open_and_following_entries_are_marked(): void
    {
        MonthClosure::factory()->create([
            'month' => '2026-06-01', 'reopened_by' => User::factory(), 'reopened_at' => now('UTC'), 'reopen_reason' => 'Correction autorisée.',
        ]);

        app(MonthGuard::class)->assertOpen('2026-06-18 10:00:00');
        $this->assertTrue(app(MonthGuard::class)->recordedAfterReopen('2026-06-18 10:00:00'));
    }
}
