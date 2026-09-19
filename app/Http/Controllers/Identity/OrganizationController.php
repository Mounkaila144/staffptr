<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\StoreDepartmentRequest;
use App\Http\Requests\Identity\StoreJobFunctionRequest;
use App\Http\Requests\Identity\UpdateCompanyRequest;
use App\Http\Requests\Identity\UpdateDepartmentRequest;
use App\Http\Requests\Identity\UpdateJobFunctionRequest;
use App\Models\Identity\Company;
use App\Models\Identity\Department;
use App\Models\Identity\JobFunction;
use App\Models\Identity\User;
use App\Services\Identity\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizationService) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Company::class);
        $this->actor($request);
        $company = Company::query()->firstOrFail();

        return Inertia::render('Identity/Organization/Index', [
            'company' => [
                'id' => $company->getKey(),
                'name' => $company->name,
                'phone' => $company->phone,
                'email' => $company->email,
                'address' => $company->address,
                'logo_url' => $company->logo_path === null
                    ? null
                    : Storage::disk('public')->url($company->logo_path),
            ],
            'departments' => Department::query()
                ->withCount(['members as active_members_count' => fn ($query) => $query->where('state', 'actif')])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']),
            'jobFunctions' => JobFunction::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']),
        ]);
    }

    public function updateCompany(UpdateCompanyRequest $request): RedirectResponse
    {
        $company = Company::query()->firstOrFail();
        Gate::authorize('update', $company);
        $attributes = $request->safe()->except('logo');
        $logo = $request->file('logo');

        if ($logo !== null && $logo->isValid()) {
            $attributes['logo_path'] = $logo->store('company', 'public');
        }

        $this->organizationService->updateCompany($company, $attributes, $this->actor($request));

        return redirect()->route('organisation.index');
    }

    public function storeDepartment(StoreDepartmentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Department::class);
        $this->organizationService->createDepartment(
            (string) $request->validated('name'),
            $this->actor($request),
        );

        return redirect()->route('organisation.index');
    }

    public function updateDepartment(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        Gate::authorize('update', $department);
        $this->organizationService->renameDepartment(
            $department,
            (string) $request->validated('name'),
            $this->actor($request),
        );

        return redirect()->route('organisation.index');
    }

    public function deactivateDepartment(Request $request, Department $department): RedirectResponse
    {
        Gate::authorize('update', $department);
        $this->organizationService->deactivateDepartment($department, $this->actor($request));

        return redirect()->route('organisation.index');
    }

    public function reactivateDepartment(Request $request, Department $department): RedirectResponse
    {
        Gate::authorize('update', $department);
        $this->organizationService->reactivateDepartment($department, $this->actor($request));

        return redirect()->route('organisation.index');
    }

    public function storeJobFunction(StoreJobFunctionRequest $request): RedirectResponse
    {
        Gate::authorize('create', JobFunction::class);
        $this->organizationService->createJobFunction(
            (string) $request->validated('name'),
            $this->actor($request),
        );

        return redirect()->route('organisation.index');
    }

    public function updateJobFunction(UpdateJobFunctionRequest $request, JobFunction $jobFunction): RedirectResponse
    {
        Gate::authorize('update', $jobFunction);
        $this->organizationService->renameJobFunction(
            $jobFunction,
            (string) $request->validated('name'),
            $this->actor($request),
        );

        return redirect()->route('organisation.index');
    }

    public function deactivateJobFunction(Request $request, JobFunction $jobFunction): RedirectResponse
    {
        Gate::authorize('update', $jobFunction);
        $this->organizationService->deactivateJobFunction($jobFunction, $this->actor($request));

        return redirect()->route('organisation.index');
    }

    public function reactivateJobFunction(Request $request, JobFunction $jobFunction): RedirectResponse
    {
        Gate::authorize('update', $jobFunction);
        $this->organizationService->reactivateJobFunction($jobFunction, $this->actor($request));

        return redirect()->route('organisation.index');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
