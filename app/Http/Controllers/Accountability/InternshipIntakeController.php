<?php

namespace App\Http\Controllers\Accountability;

use App\Enums\UserState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\ActivateInternRequest;
use App\Http\Requests\Accountability\DecideInternshipIntakeRequest;
use App\Http\Requests\Accountability\StoreInternshipIntakeRequest;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Identity\User;
use App\Services\Accountability\InternshipIntakeService;
use App\Services\Accountability\InternshipService;
use App\Services\Identity\InternActivationReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InternshipIntakeController extends Controller
{
    public function __construct(
        private readonly InternshipIntakeService $intakes,
        private readonly InternshipService $internships,
        private readonly InternActivationReadiness $readiness,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('viewAny', InternshipIntakeForm::class);
        $canCreate = $actor->can('create', InternshipIntakeForm::class);

        return Inertia::render('Accountability/Internships/Intakes/Index', [
            'forms' => $this->intakes->visibleFor($actor)->through(
                fn (InternshipIntakeForm $form): array => $this->intakes->payload($form),
            ),
            'canCreate' => $canCreate,
            // Les trois listes ne partent qu'à qui peut rédiger : un tuteur qui ne fait que
            // consulter n'a pas à recevoir l'annuaire des comptes dans ses props.
            'candidates' => $canCreate ? $this->candidateOptions() : [],
            'managers' => $canCreate ? $this->roleOptions(['direction', 'finance', 'tuteur', 'employe']) : [],
            'tutors' => $canCreate ? $this->roleOptions(['tuteur', 'direction']) : [],
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function show(Request $request, InternshipIntakeForm $internshipIntakeForm): Response
    {
        Gate::authorize('view', $internshipIntakeForm);
        $actor = $this->actor($request);

        return Inertia::render('Accountability/Internships/Intakes/Show', [
            'form' => $this->intakes->payload($internshipIntakeForm),
            // Les trois conditions sont affichées une par une, pour que le refus soit lisible.
            'readiness' => $this->readiness->status($internshipIntakeForm->candidate),
            'permissions' => [
                'submit' => $actor->can('submit', $internshipIntakeForm),
                'decide' => $actor->can('decide', $internshipIntakeForm),
                // Sans cette permission, l'écran énonçait les conditions d'activation sans
                // jamais offrir de quoi activer : le compte restait « invité » indéfiniment.
                'activate' => $actor->can('activate', Internship::class),
            ],
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function store(StoreInternshipIntakeRequest $request): RedirectResponse
    {
        /** @var list<string> $outcomes */
        $outcomes = $request->validated('outcomes');
        $form = $this->intakes->draft($this->actor($request), [
            'candidate_user_id' => (int) $request->validated('candidate_user_id'),
            'manager_id' => (int) $request->validated('manager_id'),
            'tutor_id' => (int) $request->validated('tutor_id'),
            'real_need' => (string) $request->validated('real_need'),
            'mission' => (string) $request->validated('mission'),
            'duration_weeks' => (int) $request->validated('duration_weeks'),
            'tools' => (string) $request->validated('tools'),
            'outcomes' => $outcomes,
        ]);

        return to_route('internship-intakes.show', $form)->with('success', 'Fiche d’entrée enregistrée.');
    }

    public function submit(Request $request, InternshipIntakeForm $internshipIntakeForm): RedirectResponse
    {
        Gate::authorize('submit', $internshipIntakeForm);
        $this->intakes->submit($internshipIntakeForm, $this->actor($request));

        return back()->with('success', 'Fiche soumise à la direction.');
    }

    public function decide(DecideInternshipIntakeRequest $request, InternshipIntakeForm $internshipIntakeForm): RedirectResponse
    {
        $approved = (bool) $request->validated('approved');
        $this->intakes->decide(
            $internshipIntakeForm,
            $this->actor($request),
            $approved,
            $request->validated('decision_reason'),
        );

        return back()->with('success', $approved ? 'Fiche approuvée.' : 'Fiche refusée.');
    }

    public function activate(ActivateInternRequest $request, User $user): RedirectResponse
    {
        $internship = $this->internships->activate($user, $this->actor($request));

        return to_route('internship-intakes.show', $internship->internship_intake_form_id)
            ->with('success', 'Stagiaire activé, checklist d’intégration générée.');
    }

    /**
     * Comptes de stagiaire à qui une fiche peut être rédigée.
     *
     * Un compte « invité » est précisément celui qui attend sa fiche ; un compte déjà actif y
     * figure aussi, car une seconde fiche reste possible après un premier stage. L'état est écrit
     * dans le libellé plutôt que porté par une couleur.
     *
     * @return list<array{value: int, label: string}>
     */
    private function candidateOptions(): array
    {
        return User::query()
            ->whereIn('state', [UserState::Invite->value, UserState::Actif->value])
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('name', 'stagiaire'))
            ->with('person:id,full_name')
            ->get()
            ->map(fn (User $user): array => [
                'value' => (int) $user->getKey(),
                'label' => $user->person->full_name.' — '.$user->state->label(),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $roles
     * @return list<array{value: int, label: string}>
     */
    private function roleOptions(array $roles): array
    {
        return User::query()
            ->where('state', UserState::Actif->value)
            ->whereHas('roles', fn (Builder $query): Builder => $query->whereIn('name', $roles))
            ->with('person:id,full_name')
            ->get()
            ->map(fn (User $user): array => [
                'value' => (int) $user->getKey(),
                'label' => $user->person->full_name,
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
