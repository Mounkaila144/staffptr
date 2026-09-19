<?php

namespace Tests\Feature;

use App\Models\Finance\ShareEntitlement;
use App\Services\Platform\Invariants\FinanceIntegrityInvariant;
use Tests\Support\IdentityTestCase;

class FinanceIntegrityInvariantTest extends IdentityTestCase
{
    public function test_ac_94_and_98_invariant_detects_a_share_sum_corrupted_in_the_data(): void
    {
        $share = ShareEntitlement::factory()->create(['base_amount' => 100_000, 'share_amount' => 90_000]);
        $result = app(FinanceIntegrityInvariant::class)->check();
        $this->assertFalse($result->passed);
        $this->assertStringContainsString((string) $share->payment_id, $result->observed);
        $this->artisan('ptr:check-invariants')->expectsOutputToContain('Intégrité du livre financier')->assertFailed();
    }
}
