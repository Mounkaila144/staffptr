<?php

namespace App\Providers;

use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Policies\Identity\PersonPolicy;
use App\Policies\Identity\UserPolicy;
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
        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        RateLimiter::for('login', function (Request $request): Limit {
            $phone = mb_strtolower((string) $request->input('phone'));
            $key = hash('sha256', $phone.'|'.$request->ip());

            return Limit::perMinutes(15, 5)->by($key);
        });
    }
}
