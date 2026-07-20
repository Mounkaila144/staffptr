<?php

namespace Tests\Feature;

use App\Models\Identity\LoginAttempt;
use App\Models\Identity\User;
use App\Services\Identity\AttemptedPhoneFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class LoginAttemptFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_attempts_schema_supports_history_filters_without_password_storage(): void
    {
        $columns = Schema::getColumnListing('login_attempts');

        $this->assertEqualsCanonicalizing([
            'id',
            'user_id',
            'phone_attempted',
            'successful',
            'ip_address',
            'user_agent',
            'occurred_at',
            'lock_expires_at',
        ], $columns);
        $this->assertSame([], array_values(array_filter(
            $columns,
            static fn (string $column): bool => str_contains(mb_strtolower($column), 'password'),
        )));

        $indexNames = array_column(Schema::getIndexes('login_attempts'), 'name');

        $this->assertContains('login_attempts_user_period_index', $indexNames);
        $this->assertContains('login_attempts_phone_period_index', $indexNames);
        $this->assertContains('login_attempts_ip_period_index', $indexNames);
        $this->assertContains('login_attempts_result_period_index', $indexNames);
    }

    public function test_phone_fingerprint_is_deterministic_keyed_and_never_plaintext(): void
    {
        config()->set('app.key', 'story-2.6-secret-one');
        $fingerprint = app(AttemptedPhoneFingerprint::class);
        $first = $fingerprint->for('90 12 34 56');

        $this->assertSame($first, $fingerprint->for('+22790123456'));
        $this->assertNotSame('+22790123456', $first);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $first);

        config()->set('app.key', 'story-2.6-secret-two');

        $this->assertNotSame($first, $fingerprint->for('+22790123456'));
    }

    public function test_login_attempt_belongs_to_user_and_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $attempt = LoginAttempt::factory()->for($user)->create();

        $this->assertTrue($attempt->user->is($user));
        $this->assertTrue($user->loginAttempts->first()->is($attempt));

        $this->expectException(LogicException::class);

        $attempt->delete();
    }
}
