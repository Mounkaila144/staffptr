<?php

namespace App\Http\Controllers\Accountability;

use App\Enums\InternshipEvaluationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\RecordInternshipEvaluationRequest;
use App\Http\Requests\Accountability\SaveInternshipPlanRequest;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipEvaluation;
use App\Models\Identity\User;
use App\Services\Accountability\InternshipEvaluationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InternshipController extends Controller
{
    public function __construct(private readonly InternshipEvaluationService $evaluations) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('viewAny', Internship::class);

        return Inertia::render('Accountability/Internships/Index', [
            'internships' => Internship::query()
                ->visibleTo($actor)
                ->with(['intern.person', 'tutor.person'])
                ->orderByDesc('start_date')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Internship $internship): array => [
                    'id' => $internship->getKey(),
                    'intern' => $internship->intern->person->full_name,
                    'tutor' => $internship->tutor->person->full_name,
                    'state_label' => $internship->state->label(),
                    'start_date' => $internship->start_date->format('d/m/Y'),
                    'url' => route('internships.show', $internship),
                ]),
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function show(Request $request, Internship $internship): Response
    {
        Gate::authorize('view', $internship);
        $actor = $this->actor($request);

        return Inertia::render('Accountability/Internships/Show', [
            'internship' => $this->evaluations->dossier($internship),
            'permissions' => ['manage' => $actor->can('manage', $internship)],
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function savePlan(SaveInternshipPlanRequest $request, Internship $internship): RedirectResponse
    {
        $this->evaluations->savePlan($internship, $this->actor($request), [
            'skills_to_learn' => (string) $request->validated('skills_to_learn'),
            'objectives' => (string) $request->validated('objectives'),
            'weekly_tasks' => (string) $request->validated('weekly_tasks'),
            'expected_evidence' => (string) $request->validated('expected_evidence'),
        ]);

        return back()->with('success', 'Plan de stage enregistré.');
    }

    public function recordEvaluation(RecordInternshipEvaluationRequest $request, Internship $internship): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = [
            'observed_progress' => (string) $request->validated('observed_progress'),
            'evidence' => $request->validated('evidence'),
            'next_steps' => $request->validated('next_steps'),
        ];

        if (InternshipEvaluationType::from((string) $request->validated('type')) === InternshipEvaluationType::Finale) {
            $this->evaluations->recordFinal($internship, $actor, $data);

            return back()->with('success', 'Évaluation finale enregistrée.');
        }

        $this->evaluations->recordWeekly(
            $internship,
            $actor,
            CarbonImmutable::parse((string) $request->validated('week_start_date'), 'Africa/Niamey'),
            $data,
        );

        return back()->with('success', 'Évaluation hebdomadaire enregistrée.');
    }

    public function validateEvaluation(Request $request, Internship $internship, InternshipEvaluation $evaluation): RedirectResponse
    {
        Gate::authorize('manage', $internship);
        abort_unless($evaluation->internship_id === $internship->getKey(), 404);
        $this->evaluations->validate($evaluation, $this->actor($request));

        return back()->with('success', 'Évaluation validée : elle est désormais figée.');
    }

    public function exit(Request $request, Internship $internship): RedirectResponse
    {
        Gate::authorize('manage', $internship);
        $this->evaluations->startExit($internship, $this->actor($request));

        return back()->with('success', 'Stage terminé, checklist de sortie générée.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
