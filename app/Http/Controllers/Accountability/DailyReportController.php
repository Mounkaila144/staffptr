<?php

namespace App\Http\Controllers\Accountability;

use App\Enums\UserState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\StoreDailyReportRequest;
use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;
use App\Services\Accountability\DailyReportService;
use App\Services\Platform\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DailyReportController extends Controller
{
    public function __construct(
        private readonly DailyReportService $service,
        private readonly SettingsService $settings,
    ) {}

    public function create(Request $request): Response
    {
        Gate::authorize('create', DailyReport::class);
        $actor = $this->actor($request);

        return Inertia::render('Accountability/DailyReports/Edit', [
            'daily' => $this->service->formFor($actor),
            'userId' => $actor->getKey(),
            'attachmentConfig' => [
                'attachable_id' => $actor->person_id,
                'allowed_types' => $this->settings->attachmentAllowedTypes(),
                'max_size_bytes' => $this->settings->attachmentMaxSizeBytes(),
            ],
            'solicitableUsers' => User::query()->where('state', UserState::Actif)->with('person')->get()->sortBy('person.full_name')->map(fn (User $user): array => ['id' => $user->getKey(), 'name' => $user->person->full_name])->values()->all(),
        ]);
    }

    public function store(StoreDailyReportRequest $request): RedirectResponse
    {
        $this->service->submit($request->validated(), $this->actor($request));

        return to_route('daily-reports.today')->with('success', 'Votre rapport a bien été envoyé.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
