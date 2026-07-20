<?php

namespace Tests\Feature;

use App\Models\Identity\LoginAttempt;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use Tests\Support\IdentityTestCase;

class LoginAttemptConfidentialityTest extends IdentityTestCase
{
    public function test_ac_6_passwords_are_absent_from_history_audit_and_technical_logs(): void
    {
        config()->set('login-security.max_failed_attempts', 1);
        config()->set('login-security.rate_limit_attempts', 10);
        $passwordTypedAsPhone = 'Secret-Dans-Le-Champ-Numero-2.6';
        $wrongPassword = 'Secret-Mot-De-Passe-Errone-2.6';
        $user = User::factory()->active()->create(['password' => 'Secret-Correct-2.6']);
        $lastAuditId = (int) (AuditLog::query()->max('id') ?? 0);
        $logPath = storage_path('logs/laravel.log');
        $logOffset = is_file($logPath) ? (int) filesize($logPath) : 0;

        $this->from(route('login'))->post(route('login.store'), [
            'phone' => $passwordTypedAsPhone,
            'password' => $wrongPassword,
        ]);
        $this->from(route('login'))->post(route('login.store'), [
            'phone' => $user->phone,
            'password' => $wrongPassword,
        ]);

        $serializedValues = json_encode([
            'attempts' => LoginAttempt::query()->get()->map->getAttributes()->all(),
            'audits' => AuditLog::query()->where('id', '>', $lastAuditId)->get()
                ->map(static fn (AuditLog $audit): array => [
                    'old_values' => $audit->old_values,
                    'new_values' => $audit->new_values,
                    'reason' => $audit->reason,
                ])->all(),
        ], JSON_THROW_ON_ERROR);
        $newLogContent = is_file($logPath)
            ? (string) file_get_contents($logPath, offset: $logOffset)
            : '';

        foreach ([$passwordTypedAsPhone, $wrongPassword, 'Secret-Correct-2.6'] as $secret) {
            $this->assertStringNotContainsStringIgnoringCase($secret, $serializedValues);
            $this->assertStringNotContainsStringIgnoringCase($secret, $newLogContent);
        }

        $this->assertMatchesRegularExpression(
            '/\A[a-f0-9]{64}\z/',
            (string) LoginAttempt::query()->whereNull('user_id')->value('phone_attempted'),
        );
    }
}
