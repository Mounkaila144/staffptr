<?php

namespace App\Providers;

use App\Models\Identity\LoginAttempt;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Policies\Identity\LoginAttemptPolicy;
use App\Policies\Identity\PersonPolicy;
use App\Policies\Identity\UserPolicy;
use App\Services\Identity\AttemptedPhoneFingerprint;
use App\Services\Identity\LoginSecuritySettings;
use App\Support\Auditing\AuditContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(LoginAttempt::class, LoginAttemptPolicy::class);
        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        RateLimiter::for('login', function (Request $request): array {
            $fingerprint = app(AttemptedPhoneFingerprint::class);
            $settings = app(LoginSecuritySettings::class);
            $phoneKey = $fingerprint->for((string) $request->input('phone'));
            $ipKey = $fingerprint->for((string) $request->ip());

            return [
                Limit::perMinute($settings->rateLimitAttempts())->by("login:phone:{$phoneKey}"),
                Limit::perMinute($settings->rateLimitAttempts())->by("login:ip:{$ipKey}"),
            ];
        });
    }
}
