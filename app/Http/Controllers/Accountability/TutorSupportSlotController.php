<?php

namespace App\Http\Controllers\Accountability;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\StoreTutorSupportSlotRequest;
use App\Models\Accountability\Internship;
use App\Models\Accountability\TutorSupportSlot;
use App\Models\Identity\User;
use App\Services\Accountability\SupportRequestGroupingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TutorSupportSlotController extends Controller
{
    public function __construct(private readonly SupportRequestGroupingService $grouping) {}

    /**
     * Créneaux de suivi du tuteur connecté (AC 34) et, pour un stagiaire, le moment où ses
     * demandes seront examinées (AC 37).
     */
    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $next = $this->grouping->nextSlotFor($actor, CarbonImmutable::now('UTC'));

        return Inertia::render('Accountability/Internships/Slots/Index', [
            'slots' => $this->grouping->slotsFor($actor),
            'nextSlot' => $next instanceof CarbonImmutable
                ? $next->setTimezone('Africa/Niamey')->format('d/m/Y H:i')
                : null,
            'pendingRequests' => $this->grouping->pendingFor($actor),
            'canManage' => $actor->can('viewCapacity', Internship::class),
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function store(StoreTutorSupportSlotRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);

        TutorSupportSlot::query()->firstOrCreate([
            'tutor_id' => $actor->getKey(),
            'weekday' => (int) $request->validated('weekday'),
            'start_time' => $request->validated('start_time').':00',
        ]);

        return back()->with('success', 'Créneau de suivi enregistré.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
