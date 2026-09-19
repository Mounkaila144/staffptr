<?php

namespace App\Providers;

use App\Models\Accountability\Blocker;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Accountability\ImprovementPlan;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Accountability\TaskRequest;
use App\Models\Accountability\WeeklyReview;
use App\Models\Finance\Account as FinancialAccount;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\Invoice;
use App\Models\Finance\MonthClosure;
use App\Models\Finance\MonthlyBudget;
use App\Models\Finance\MonthlyReport;
use App\Models\Finance\Payment;
use App\Models\Finance\Reconciliation;
use App\Models\Finance\ReserveMovement;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\Absence;
use App\Models\Identity\Company;
use App\Models\Identity\Department;
use App\Models\Identity\JobFunction;
use App\Models\Identity\LoginAttempt;
use App\Models\Identity\Person;
use App\Models\Identity\PersonDocument;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Models\Platform\Holiday;
use App\Models\Platform\InternalDocument;
use App\Models\Platform\InternalDocumentVersion;
use App\Models\Platform\SavedFilter;
use App\Models\Platform\Setting;
use App\Models\Work\CompanyPriority;
use App\Models\Work\Deliverable;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use App\Models\Work\Task;
use App\Observers\AlertLevelCacheObserver;
use App\Policies\Accountability\BlockerPolicy;
use App\Policies\Accountability\DailyReportPolicy;
use App\Policies\Accountability\DailyReportVersionPolicy;
use App\Policies\Accountability\ImprovementPlanPolicy;
use App\Policies\Accountability\InternshipIntakeFormPolicy;
use App\Policies\Accountability\InternshipPolicy;
use App\Policies\Accountability\TaskRequestPolicy;
use App\Policies\Accountability\WeeklyReviewPolicy;
use App\Policies\Finance\AccountPolicy as FinancialAccountPolicy;
use App\Policies\Finance\ClientPolicy;
use App\Policies\Finance\ContractPolicy;
use App\Policies\Finance\ExpenseCategoryPolicy;
use App\Policies\Finance\ExpensePolicy;
use App\Policies\Finance\FixedChargePolicy;
use App\Policies\Finance\InvoicePolicy;
use App\Policies\Finance\MonthlyBudgetPolicy;
use App\Policies\Finance\MonthlyReportPolicy;
use App\Policies\Finance\PaymentPolicy;
use App\Policies\Finance\ReconciliationPolicy;
use App\Policies\Finance\ReserveMovementPolicy;
use App\Policies\Finance\ShareEntitlementPolicy;
use App\Policies\Identity\AbsencePolicy;
use App\Policies\Identity\CompanyPolicy;
use App\Policies\Identity\DepartmentPolicy;
use App\Policies\Identity\JobFunctionPolicy;
use App\Policies\Identity\LoginAttemptPolicy;
use App\Policies\Identity\PersonDocumentPolicy;
use App\Policies\Identity\PersonPolicy;
use App\Policies\Identity\UserPolicy;
use App\Policies\Platform\AuditLogPolicy;
use App\Policies\Platform\HolidayPolicy;
use App\Policies\Platform\InternalDocumentPolicy;
use App\Policies\Platform\InternalDocumentVersionPolicy;
use App\Policies\Platform\SavedFilterPolicy;
use App\Policies\Platform\SettingPolicy;
use App\Policies\Work\CompanyPriorityPolicy;
use App\Policies\Work\DeliverablePolicy;
use App\Policies\Work\ObjectivePolicy;
use App\Policies\Work\ProjectPolicy;
use App\Policies\Work\TaskPolicy;
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
        Gate::policy(DailyReport::class, DailyReportPolicy::class);
        Gate::policy(DailyReportVersion::class, DailyReportVersionPolicy::class);
        Gate::policy(Blocker::class, BlockerPolicy::class);
        Gate::policy(TaskRequest::class, TaskRequestPolicy::class);
        Gate::policy(WeeklyReview::class, WeeklyReviewPolicy::class);
        Gate::policy(ImprovementPlan::class, ImprovementPlanPolicy::class);
        Gate::policy(InternshipIntakeForm::class, InternshipIntakeFormPolicy::class);
        Gate::policy(Internship::class, InternshipPolicy::class);
        Gate::policy(Absence::class, AbsencePolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(JobFunction::class, JobFunctionPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Holiday::class, HolidayPolicy::class);
        Gate::policy(InternalDocument::class, InternalDocumentPolicy::class);
        Gate::policy(InternalDocumentVersion::class, InternalDocumentVersionPolicy::class);
        Gate::policy(SavedFilter::class, SavedFilterPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(LoginAttempt::class, LoginAttemptPolicy::class);
        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(PersonDocument::class, PersonDocumentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ExpenseCategory::class, ExpenseCategoryPolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(FinancialAccount::class, FinancialAccountPolicy::class);
        Gate::policy(FixedCharge::class, FixedChargePolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(MonthlyBudget::class, MonthlyBudgetPolicy::class);
        Gate::policy(MonthlyReport::class, MonthlyReportPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(ReserveMovement::class, ReserveMovementPolicy::class);
        Gate::policy(Reconciliation::class, ReconciliationPolicy::class);
        Gate::policy(ShareEntitlement::class, ShareEntitlementPolicy::class);
        Gate::policy(CompanyPriority::class, CompanyPriorityPolicy::class);
        Gate::policy(Objective::class, ObjectivePolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Deliverable::class, DeliverablePolicy::class);

        // Le cache d'alerte est invalidé par l'écriture qui le périme — encaissement, charge fixe
        // ou clôture — et jamais par la seule expiration (architecture § 19.1, AC 2, AC 3).
        Payment::observe(AlertLevelCacheObserver::class);
        FixedCharge::observe(AlertLevelCacheObserver::class);
        MonthClosure::observe(AlertLevelCacheObserver::class);

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
