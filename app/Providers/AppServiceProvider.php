<?php

namespace App\Providers;

use App\Models\Identity\Company;
use App\Models\Identity\Department;
use App\Models\Identity\JobFunction;
use App\Models\Identity\LoginAttempt;
use App\Models\Identity\Person;
use App\Models\Identity\PersonDocument;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Models\Platform\Setting;
use App\Policies\Identity\CompanyPolicy;
use App\Policies\Identity\DepartmentPolicy;
use App\Policies\Identity\JobFunctionPolicy;
use App\Policies\Identity\LoginAttemptPolicy;
use App\Policies\Identity\PersonDocumentPolicy;
use App\Policies\Identity\PersonPolicy;
use App\Policies\Identity\UserPolicy;
use App\Policies\Platform\AuditLogPolicy;
use App\Policies\Platform\SettingPolicy;
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
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(JobFunction::class, JobFunctionPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(LoginAttempt::class, LoginAttemptPolicy::class);
        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(PersonDocument::class, PersonDocumentPolicy::class);
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
