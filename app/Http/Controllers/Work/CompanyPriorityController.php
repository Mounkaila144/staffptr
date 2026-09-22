<?php

namespace App\Http\Controllers\Work;

use App\Enums\CompanyPriorityState;
use App\Enums\WorkPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Work\CancelCompanyPriorityRequest;
use App\Http\Requests\Work\CompanyPriorityIndexRequest;
use App\Http\Requests\Work\StoreCompanyPriorityRequest;
use App\Http\Requests\Work\UpdateCompanyPriorityRequest;
use App\Models\Identity\User;
use App\Models\Work\CompanyPriority;
use App\Services\Work\CompanyPriorityService;
use App\Support\Work\AssignablePeople;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyPriorityController extends Controller
{
    public function __construct(private readonly CompanyPriorityService $service) {}

    public function index(CompanyPriorityIndexRequest $request): Response
    {
        Gate::authorize('viewAny', CompanyPriority::class);
        $month = $this->month($request->validated('month'));
        $items = CompanyPriority::query()->select(['id', 'month', 'title', 'description', 'owner_id', 'indicator', 'target', 'due_date', 'priority', 'state', 'cancellation_reason'])->with('owner:id,person_id')->with('owner.person:id,full_name')->forMonth($month)->orderByDesc('priority')->orderBy('due_date')->get();

        return Inertia::render('Work/CompanyPriorities/Index', ['month' => $month->format('Y-m'), 'monthLabel' => $month->locale('fr')->isoFormat('MMMM YYYY'), 'priorities' => $items->map(fn (CompanyPriority $item): array => $this->serialize($item))->all(), 'canManage' => $request->user()?->can('create', CompanyPriority::class) ?? false, 'emptyMessage' => 'Aucune priorité définie pour '.$month->locale('fr')->isoFormat('MMMM').'.', 'priorityOptions' => $this->options(WorkPriority::cases()), 'assignablePeople' => AssignablePeople::options()]);
    }

    public function store(StoreCompanyPriorityRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $this->actor($request));

        return back()->with('success', 'La priorité a été créée.');
    }

    public function update(UpdateCompanyPriorityRequest $request, CompanyPriority $companyPriority): RedirectResponse
    {
        $data = $request->validated();
        $this->service->update($companyPriority, $data, (string) $data['reason'], $this->actor($request));

        return back()->with('success', 'La priorité a été mise à jour.');
    }

    public function cancel(CancelCompanyPriorityRequest $request, CompanyPriority $companyPriority): RedirectResponse
    {
        $this->service->cancel($companyPriority, (string) $request->validated('reason'), $this->actor($request));

        return back()->with('success', 'La priorité a été annulée.');
    }

    private function month(mixed $value): CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::createFromFormat('!Y-m', $value, 'Africa/Niamey') : CarbonImmutable::now('Africa/Niamey')->startOfMonth();
    }

    /** @return array<string, mixed> */
    private function serialize(CompanyPriority $item): array
    {
        return ['id' => $item->getKey(), 'title' => $item->title, 'description' => $item->description, 'owner' => $item->owner->person->full_name, 'indicator' => $item->indicator, 'target' => $item->target, 'due_date' => $item->due_date->format('d/m/Y'), 'priority' => $item->priority->value, 'priority_label' => $item->priority->label(), 'state' => $item->state->value, 'state_label' => $item->state->label()];
    }

    /**
     * @param  list<CompanyPriorityState|WorkPriority>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(static fn ($case): array => ['value' => $case->value, 'label' => $case->label()], $cases);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
