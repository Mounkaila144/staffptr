<?php

namespace App\Http\Controllers\Accountability;

use App\Enums\ReviewObjectiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accountability\CommentWeeklyReviewRequest;
use App\Http\Requests\Accountability\OpenWeeklyReviewRequest;
use App\Http\Requests\Accountability\RecordWeeklyReviewObjectiveRequest;
use App\Http\Requests\Accountability\ValidateWeeklyReviewRequest;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Services\Accountability\WeeklyReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyReviewController extends Controller
{
    public function __construct(private readonly WeeklyReviewService $reviews) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('viewAny', WeeklyReview::class);
        $history = $this->reviews->history($actor);

        return Inertia::render('Accountability/WeeklyReviews/Index', [
            'reviews' => $history->through(fn (WeeklyReview $review): array => [
                'id' => $review->getKey(),
                'subject' => $review->subject->person->full_name,
                'reviewer' => $review->reviewer->person->full_name,
                'week_start_date' => $review->week_start_date->format('d/m/Y'),
                'scheduled_on' => $review->scheduled_on->format('d/m/Y'),
                'state' => $review->state->value,
                'state_label' => $review->state->label(),
                'url' => route('weekly-reviews.show', $review),
            ]),
            'canOpen' => $actor->can('create', WeeklyReview::class),
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function show(Request $request, WeeklyReview $weeklyReview): Response
    {
        Gate::authorize('view', $weeklyReview);
        $actor = $this->actor($request);

        return Inertia::render('Accountability/WeeklyReviews/Show', [
            'review' => $this->reviews->payload($weeklyReview),
            'facts' => $this->reviews->weekFacts($weeklyReview),
            'permissions' => [
                'update' => $actor->can('update', $weeklyReview),
                'comment' => $actor->can('comment', $weeklyReview),
                'validate' => $actor->can('validateReview', $weeklyReview),
            ],
            'success' => fn (): ?string => $request->session()->get('success'),
        ]);
    }

    public function store(OpenWeeklyReviewRequest $request): RedirectResponse
    {
        $subject = User::query()->findOrFail($request->validated('subject_user_id'));
        $review = $this->reviews->open(
            $this->actor($request),
            $subject,
            CarbonImmutable::parse((string) $request->validated('week_start_date'), 'Africa/Niamey'),
        );

        return to_route('weekly-reviews.show', $review)->with('success', 'Revue ouverte.');
    }

    public function recordObjective(RecordWeeklyReviewObjectiveRequest $request, WeeklyReview $weeklyReview): RedirectResponse
    {
        $objective = Objective::query()->findOrFail($request->validated('objective_id'));
        $this->reviews->recordObjective(
            $weeklyReview,
            $objective,
            [
                'result' => (string) $request->validated('result'),
                'evidence' => $request->validated('evidence'),
                'status' => ReviewObjectiveStatus::from((string) $request->validated('status')),
                'gap_cause' => $request->validated('gap_cause'),
                'next_action' => (string) $request->validated('next_action'),
            ],
            $this->actor($request),
        );

        return back()->with('success', 'Constat enregistré.');
    }

    public function comment(CommentWeeklyReviewRequest $request, WeeklyReview $weeklyReview): RedirectResponse
    {
        $this->reviews->comment($weeklyReview, $this->actor($request), (string) $request->validated('body'));

        return back()->with('success', 'Commentaire enregistré.');
    }

    public function submit(ValidateWeeklyReviewRequest $request, WeeklyReview $weeklyReview): RedirectResponse
    {
        Gate::authorize('update', $weeklyReview);
        $this->reviews->submitForValidation($weeklyReview, $this->actor($request));

        return back()->with('success', 'Revue soumise aux deux validations.');
    }

    public function validateReview(ValidateWeeklyReviewRequest $request, WeeklyReview $weeklyReview): RedirectResponse
    {
        $this->reviews->validateAs($weeklyReview, $this->actor($request));

        return back()->with('success', 'Validation enregistrée.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
