<?php

namespace App\Http\Controllers\Accountability;

use App\Enums\InternshipIntakeState;
use App\Enums\UserState;
use App\Enums\WorkPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\ActivateInternRequest;
use App\Http\Requests\Accountability\DecideInternshipIntakeRequest;
use App\Http\Requests\Accountability\StoreIntakeObjectiveRequest;
use App\Http\Requests\Accountability\StoreInternshipIntakeRequest;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Services\Accountability\InternshipIntakeService;
use App\Services\Accountability\InternshipService;
use App\Services\Accountability\TutorCapacityService;
use App\Services\Identity\InternActivationReadiness;
use App\Services\Work\ObjectiveService;
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
        private readonly ObjectiveService $objectives,
        private readonly TutorCapacityService $tutorCapacity,
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
            'awaiting' => $canCreate ? $this->awaitingInterns() : [],
            'managers' => $canCreate ? $this->roleOptions(['direction', 'finance', 'tuteur', 'employe']) : [],
            'tutors' => $canCreate ? $this->tutorOptions() : [],
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function show(Request $request, InternshipIntakeForm $internshipIntakeForm): Response
    {
        Gate::authorize('view', $internshipIntakeForm);
        $actor = $this->actor($request);
        $alreadyActivated = Internship::query()->where('user_id', $internshipIntakeForm->candidate_user_id)->exists();
        $tutor = $internshipIntakeForm->tutor;

        return Inertia::render('Accountability/Internships/Intakes/Show', [
            'form' => $this->intakes->payload($internshipIntakeForm),
            // Les trois conditions sont affichées une par une, pour que le refus soit lisible.
            'readiness' => $this->readiness->status($internshipIntakeForm->candidate),
            // Un stagiaire déjà activé ne peut pas l'être deux fois : sans cette information,
            // l'écran offrait un bouton que le serveur refusait sans que rien ne s'affiche.
            'already_activated' => $alreadyActivated,
            // Le refus pour tuteur complet n'était découvert qu'au clic, et sans message affiché.
            'tutor_at_limit' => ! $alreadyActivated && $tutor instanceof User && $this->tutorCapacity->hasReachedLimit($tutor)
                ? $this->tutorCapacity->limitReachedMessage($tutor)
                : null,
            // Les objectifs se créent depuis cette fiche : la direction n'a plus à changer d'écran.
            'objectives' => Objective::query()->where('user_id', $internshipIntakeForm->candidate_user_id)->orderBy('id')->get()
                ->map(fn (Objective $objective): array => [
                    'id' => (int) $objective->getKey(),
                    'title' => $objective->title,
                    'due_date' => $objective->due_date->format('d/m/Y'),
                    'state_label' => $objective->state->label(),
                ])->values()->all(),
            'priorities' => array_map(fn (WorkPriority $priority): array => ['value' => $priority->value, 'label' => $priority->label()], WorkPriority::cases()),
            'default_due_date' => now()->addWeeks($internshipIntakeForm->duration_weeks)->format('Y-m-d'),
            'permissions' => [
                'submit' => $actor->can('submit', $internshipIntakeForm),
                'decide' => $actor->can('decide', $internshipIntakeForm),
                // Sans cette permission, l'écran énonçait les conditions d'activation sans
                // jamais offrir de quoi activer : le compte restait « invité » indéfiniment.
                'activate' => $actor->can('activate', Internship::class),
                'add_objective' => $actor->can('stagiaire.gerer') && $actor->can('create', Objective::class),
            ],
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

    public function storeObjective(StoreIntakeObjectiveRequest $request, InternshipIntakeForm $internshipIntakeForm): RedirectResponse
    {
        $this->objectives->propose($request->validated(), $this->actor($request));
        $count = $this->readiness->objectiveCount($internshipIntakeForm->candidate);

        return back()->with('success', sprintf('Objectif ajouté (%d sur 3).', min($count, 3)));
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
     * Un compte « invité » est précisément celui qui attend sa fiche. Un compte qui a déjà un
     * stage n'y figure plus : l'activation le refuserait, et deux stagiaires au même prénom se
     * confondaient dans la liste. L'état est écrit dans le libellé plutôt que porté par une couleur.
     *
     * @return list<array{value: int, label: string}>
     */
    private function candidateOptions(): array
    {
        return User::query()
            ->whereIn('state', [UserState::Invite->value, UserState::Actif->value])
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('name', 'stagiaire'))
            ->whereNotIn('id', Internship::query()->select('user_id'))
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
     * Comptes de stagiaire encore invités, avec l'étape où ils en sont.
     *
     * Deux stagiaires au prénom voisin se confondaient : la direction remplissait la fiche du
     * compte déjà actif et ne voyait nulle part que le nouveau attendait toujours la sienne.
     *
     * @return list<array{id: int, name: string, phone: string, form_id: int|null, form_refused: bool, step: string}>
     */
    private function awaitingInterns(): array
    {
        return User::query()
            ->where('state', UserState::Invite->value)
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('name', 'stagiaire'))
            ->whereNotIn('id', Internship::query()->select('user_id'))
            ->with('person:id,full_name')
            ->get()
            ->map(function (User $user): array {
                $form = InternshipIntakeForm::query()->where('candidate_user_id', $user->getKey())->latest('id')->first();

                return [
                    'id' => (int) $user->getKey(),
                    'name' => $user->person->full_name,
                    'phone' => (string) $user->phone,
                    'form_id' => $form instanceof InternshipIntakeForm ? (int) $form->getKey() : null,
                    // Une fiche refusée ne se corrige pas : il faut en rédiger une nouvelle. Sans
                    // cette indication, l'encadré ne menait qu'à la fiche refusée, sans issue.
                    'form_refused' => $form instanceof InternshipIntakeForm && $form->state === InternshipIntakeState::Refusee,
                    'step' => match (true) {
                        ! $form instanceof InternshipIntakeForm => 'Aucune fiche d’entrée : le compte ne peut pas encore être activé',
                        $form->state === InternshipIntakeState::Refusee => 'Fiche refusée — rédigez une nouvelle fiche pour pouvoir l’activer',
                        default => 'Fiche '.mb_strtolower($form->state->label()).' — activation en attente',
                    },
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Tuteurs possibles, avec leur charge écrite dans le libellé : un tuteur complet reste
     * visible mais se reconnaît avant qu'on le choisisse.
     *
     * @return list<array{value: int, label: string}>
     */
    private function tutorOptions(): array
    {
        $limit = $this->tutorCapacity->limit();

        return array_map(function (array $option) use ($limit): array {
            $tutor = User::query()->findOrFail($option['value']);
            $load = $this->tutorCapacity->currentLoad($tutor);

            return [
                'value' => $option['value'],
                'label' => $load >= $limit
                    ? sprintf('%s — complet (%d stagiaires sur %d)', $option['label'], $load, $limit)
                    : sprintf('%s — %d stagiaire%s sur %d', $option['label'], $load, $load > 1 ? 's' : '', $limit),
            ];
        }, $this->roleOptions(['tuteur', 'direction']));
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
