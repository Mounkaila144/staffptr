<?php

namespace App\Http\Controllers\Accountability;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\ProcessTaskRequestRequest;
use App\Http\Requests\Accountability\StoreTaskRequestRequest;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\TaskRequest;
use App\Models\Identity\User;
use App\Services\Accountability\TaskRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskRequestController extends Controller
{
    public function __construct(private readonly TaskRequestService $service) {}

    public function store(StoreTaskRequestRequest $request, DailyReport $dailyReport): RedirectResponse
    {
        $this->service->create($dailyReport, $this->actor($request), (string) $request->validated('description'), (bool) $request->validated('is_urgent'));

        return back()->with('success', 'Votre demande de nouvelle tâche a été transmise.');
    }

    public function process(ProcessTaskRequestRequest $request, TaskRequest $taskRequest): RedirectResponse
    {
        $this->service->process($taskRequest, $this->actor($request));

        return back()->with('success', 'La demande de tâche est marquée comme traitée.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
