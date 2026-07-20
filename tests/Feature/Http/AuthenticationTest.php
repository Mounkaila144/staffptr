<?php

namespace Tests\Feature\Http;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use Illuminate\Support\Facades\Hash;
use Tests\Support\IdentityTestCase;

class AuthenticationTest extends IdentityTestCase
{
    public function test_ac_2_three_phone_forms_authenticate_the_same_account(): void
    {
        $password = 'MotDePasse-Test-2026';
        $user = User::factory()->active()->create([
            'phone' => '+22790123456',
            'password' => $password,
        ]);

        foreach (['90 12 34 56', '+22790123456', '0022790123456'] as $phone) {
            $this->post(route('login.store'), [
                'phone' => $phone,
                'password' => $password,
            ])->assertRedirect(route('home', absolute: false));

            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_ac_3_password_is_bcrypt_hashed_with_a_parameterized_cost(): void
    {
        $plainPassword = 'MotDePasse-Parametrable-2026';
        $user = User::factory()->create(['password' => $plainPassword]);
        $hashingConfiguration = (string) file_get_contents(config_path('hashing.php'));

        $this->assertSame('bcrypt', config('hashing.driver'));
        $this->assertStringContainsString("env('BCRYPT_ROUNDS', 12)", $hashingConfiguration);
        $this->assertNotSame($plainPassword, $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check($plainPassword, $user->password));
    }

    public function test_ac_5_unknown_phone_and_wrong_password_are_indistinguishable(): void
    {
        $wrongPassword = 'Secret-Errone-2.4';
        $user = User::factory()->active()->create([
            'phone' => '+22790123456',
            'password' => 'Secret-Correct-2.4',
        ]);
        $lastAuditId = (int) (AuditLog::query()->max('id') ?? 0);
        $logPath = storage_path('logs/laravel.log');
        $logOffset = is_file($logPath) ? (int) filesize($logPath) : 0;

        $wrongResponse = $this->from(route('login'))->post(route('login.store'), [
            'phone' => $user->phone,
            'password' => $wrongPassword,
        ]);
        $wrongErrors = serialize($wrongResponse->getSession()->get('errors'));
        $wrongResponse->assertSessionHasErrors(['phone' => 'Numéro ou mot de passe incorrect.']);
        $this->assertGuest();
        session()->flush();

        $unknownResponse = $this->from(route('login'))->post(route('login.store'), [
            'phone' => '+22790999999',
            'password' => $wrongPassword,
        ]);
        $unknownErrors = serialize($unknownResponse->getSession()->get('errors'));
        $unknownResponse->assertSessionHasErrors(['phone' => 'Numéro ou mot de passe incorrect.']);

        $this->assertSame($wrongResponse->getStatusCode(), $unknownResponse->getStatusCode());
        $this->assertSame($wrongResponse->headers->get('Location'), $unknownResponse->headers->get('Location'));
        $this->assertSame($wrongErrors, $unknownErrors);
        $this->assertGuest();
        $this->assertSame(0, AuditLog::query()->where('id', '>', $lastAuditId)->count());

        $newLogContent = is_file($logPath)
            ? (string) file_get_contents($logPath, offset: $logOffset)
            : '';
        $this->assertStringNotContainsStringIgnoringCase($wrongPassword, $newLogContent);
    }

    public function test_ac_5_account_state_is_checked_only_after_valid_credentials(): void
    {
        $password = 'MotDePasse-Suspendu-2026';
        $user = User::factory()->create([
            'state' => UserState::Suspendu,
            'phone' => '+22790123456',
            'password' => $password,
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'phone' => $user->phone,
            'password' => 'mot-de-passe-faux',
        ])->assertSessionHasErrors(['phone' => 'Numéro ou mot de passe incorrect.']);

        $this->from(route('login'))->post(route('login.store'), [
            'phone' => $user->phone,
            'password' => $password,
        ])->assertSessionHasErrors([
            'phone' => "Votre compte n'est pas actif. Contactez la direction.",
        ]);

        $this->assertGuest();
    }
}
