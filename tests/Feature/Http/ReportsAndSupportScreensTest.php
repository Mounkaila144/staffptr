<?php

namespace Tests\Feature\Http;

use App\Enums\BlockerUrgency;
use App\Enums\DailyReportDecisionType;
use App\Enums\DailyReportState;
use App\Enums\TaskRequestState;
use App\Enums\WeeklyReviewState;
use App\Exceptions\Identity\EvolutionApiUnavailable;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Accountability\TaskRequest;
use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Task;
use App\Services\Accountability\DailyReportReviewService;
use App\Services\Accountability\DailyReportService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Défauts relevés en testant les écrans « Rapports et accompagnement » dans le navigateur :
 * dates brutes, motif de retour invisible pour l'auteur, origine de blocage saisie par numéro,
 * demandes de tâche introuvables, revue hebdomadaire impossible à ouvrir depuis l'écran.
 */
class ReportsAndSupportScreensTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_the_daily_form_shows_a_readable_date_and_offers_the_lateness_explanation_once_the_deadline_has_passed(): void
    {
        $actor = User::factory()->active()->create();

        $before = $this->dailyService()->formFor($actor, CarbonImmutable::parse('2026-08-11 16:00', 'Africa/Niamey'));
        $after = $this->dailyService()->formFor($actor, CarbonImmutable::parse('2026-08-11 19:00', 'Africa/Niamey'));

        $this->assertSame('Mardi 11 août 2026', $before['date_label']);
        $this->assertFalse($before['deadline_passed']);
        $this->assertTrue($after['deadline_passed']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $after['deadline_time']);
    }

    public function test_the_author_reads_the_return_reason_and_the_reviewer_comments_on_the_daily_form(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-11 16:00', 'Africa/Niamey'));
        $author = User::factory()->active()->withRole('employe')->create();
        $reviewer = User::factory()->active()->withRole('direction')->create();
        $report = $this->dailyService()->submit($this->validData(), $author);
        $reviews = $this->app->make(DailyReportReviewService::class);
        $reviews->comment($report, $reviewer, 'Précisez le reste à faire.');
        $reviews->decide($report, $reviewer, DailyReportDecisionType::Retourner, 'Il manque le reste à faire.');

        $form = $this->dailyService()->formFor($author);

        $this->assertSame(DailyReportState::Retourne->value, $form['report']['state']);
        $this->assertSame('Il manque le reste à faire.', $form['report']['return_reason']);
        $this->assertSame('Précisez le reste à faire.', $form['report']['comments'][0]['body']);
    }

    public function test_opening_the_daily_form_marks_the_notifications_leading_to_it_as_read(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-11 16:00', 'Africa/Niamey'));
        $author = User::factory()->active()->withRole('employe')->create();
        $reviewer = User::factory()->active()->withRole('direction')->create();
        $report = $this->dailyService()->submit($this->validData(), $author);
        $this->app->make(DailyReportReviewService::class)->decide($report, $reviewer, DailyReportDecisionType::Retourner, 'À préciser.');
        $this->assertSame(1, $author->unreadNotifications()->count());

        $this->actingAs($author)->get(route('daily-reports.today'))->assertOk();

        $this->assertSame(0, $author->fresh()->unreadNotifications()->count());
    }

    public function test_the_blocker_screen_offers_the_actor_own_work_as_origins_and_readable_states(): void
    {
        $actor = User::factory()->active()->withRole('employe')->create();
        $solicited = User::factory()->active()->withRole('employe')->create();
        $task = Task::factory()->create(['assignee_id' => $actor->getKey(), 'title' => 'Rédiger le devis']);
        Task::factory()->create(['title' => 'Tâche de quelqu’un d’autre']);
        $objective = Objective::factory()->create(['user_id' => $actor->getKey(), 'state' => 'en_cours']);
        Blocker::factory()->create([
            'origin_type' => Task::class, 'origin_id' => $task->getKey(),
            'created_by' => $actor->getKey(), 'solicited_user_id' => $solicited->getKey(),
            'urgency' => BlockerUrgency::Urgente, 'state' => 'pris_en_charge',
        ]);

        $this->actingAs($actor)
            ->get(route('blockers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('origins', fn (Collection $origins): bool => $origins->contains(fn (array $origin): bool => $origin['type'] === 'task' && $origin['id'] === $task->getKey() && str_contains($origin['label'], 'Rédiger le devis'))
                    && $origins->contains(fn (array $origin): bool => $origin['type'] === 'objective' && $origin['id'] === $objective->getKey())
                    && ! $origins->contains(fn (array $origin): bool => str_contains($origin['label'], 'quelqu’un d’autre')))
                ->where('blockers.0.state_label', 'Pris en charge')
                ->where('blockers.0.urgency_label', 'Urgente')
                ->where('users', fn (Collection $users): bool => ! $users->contains('id', $actor->getKey())));
    }

    public function test_a_blocker_is_reported_even_when_the_whatsapp_gateway_is_unavailable(): void
    {
        // File synchrone (phpunit.xml) et livraison réelle interdite hors production : le canal
        // WhatsApp lève `EvolutionApiUnavailable`, comme sur un poste local ou une passerelle en panne.
        $actor = User::factory()->active()->withRole('employe')->create();
        $solicited = User::factory()->active()->withRole('direction')->create();
        $task = Task::factory()->create(['assignee_id' => $actor->getKey()]);
        Exceptions::fake();

        $this->actingAs($actor)
            ->from(route('blockers.index'))
            ->post(route('blockers.store'), [
                'origin_type' => 'task', 'origin_id' => $task->getKey(), 'problem' => 'Accès refusé',
                'urgency' => 'urgente', 'solicited_user_id' => $solicited->getKey(), 'reported_on' => '2026-08-11',
                'deadline_impact' => 'Retard', 'attempted_action' => 'Relance',
            ])
            ->assertRedirect(route('blockers.index'))
            ->assertSessionHas('success', 'Le blocage a été signalé.');

        Exceptions::assertReported(EvolutionApiUnavailable::class);
        $this->assertDatabaseCount('blockers', 1);
        $this->assertSame(1, DatabaseNotification::query()->where('notifiable_id', $solicited->getKey())->count());
    }

    public function test_blocker_validation_messages_name_the_missing_field(): void
    {
        $actor = User::factory()->active()->withRole('employe')->create();

        $this->actingAs($actor)
            ->from(route('blockers.index'))
            ->post(route('blockers.store'), ['urgency' => 'normale', 'reported_on' => '2026-08-11'])
            ->assertSessionHasErrors([
                'origin_id' => 'Choisissez ce qui est bloqué : une tâche, un objectif ou un rapport.',
                'problem' => 'Décrivez le problème rencontré.',
                'solicited_user_id' => 'Choisissez la personne dont vous sollicitez l’aide.',
            ]);
    }

    public function test_the_review_queue_shows_task_requests_so_the_reviewer_can_process_them(): void
    {
        $author = User::factory()->active()->withRole('employe')->create();
        $reviewer = User::factory()->active()->withRole('direction')->create();
        $report = DailyReport::factory()->submitted()->create(['author_id' => $author->getKey()]);
        DailyReportVersion::factory()->create(['daily_report_id' => $report->getKey(), 'author_id' => $author->getKey()]);
        $taskRequest = TaskRequest::factory()->create([
            'daily_report_id' => $report->getKey(), 'requested_by' => $author->getKey(),
            'responsible_id' => $reviewer->getKey(), 'description' => 'Une tâche sur le site', 'is_urgent' => true,
            'state' => TaskRequestState::Ouvert,
        ]);

        $this->actingAs($reviewer)
            ->get(route('daily-report-reviews.show', $report))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('reports.0.task_requests.0.id', $taskRequest->getKey())
                ->where('reports.0.task_requests.0.description', 'Une tâche sur le site')
                ->where('reports.0.task_requests.0.is_open', true));

        $this->actingAs($reviewer)
            ->patch(route('task-requests.process', $taskRequest))
            ->assertSessionHas('success');
        $this->assertSame(TaskRequestState::Traite, $taskRequest->fresh()->state);
    }

    public function test_the_daily_report_views_expose_readable_period_bounds(): void
    {
        $actor = User::factory()->active()->withRole('direction')->create();

        $this->actingAs($actor)
            ->get(route('daily-reports.index', ['vue' => 'hebdomadaire', 'date' => '2026-08-12']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('analytics.from_label', '10/08/2026')
                ->where('analytics.to_label', '16/08/2026'));
    }

    public function test_the_weekly_review_screens_carry_what_their_forms_need(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $subject = User::factory()->active()->withRole('employe')->create();
        User::factory()->create(['state' => 'archive']);

        $this->actingAs($direction)
            ->get(route('weekly-reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('canOpen', true)
                ->where('subjects', fn (Collection $subjects): bool => $subjects->contains('id', $subject->getKey())
                    && ! $subjects->contains('id', $direction->getKey()))
                ->has('defaultWeek'));

        $review = WeeklyReview::factory()->create([
            'subject_user_id' => $subject->getKey(), 'reviewer_id' => $direction->getKey(),
            'state' => WeeklyReviewState::Brouillon,
        ]);

        $this->actingAs($direction)
            ->get(route('weekly-reviews.show', $review))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('permissions.submit', true)
                ->where('permissions.createPlan', true)
                ->has('objectiveStatuses', 3)
                ->where('objectiveStatuses.0.requires_gap_cause', false)
                ->has('plans', 0));

    }

    public function test_the_reviewed_person_sees_neither_the_opening_form_nor_the_reviewer_actions(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $subject = User::factory()->active()->withRole('employe')->create();
        $review = WeeklyReview::factory()->create([
            'subject_user_id' => $subject->getKey(), 'reviewer_id' => $direction->getKey(),
            'state' => WeeklyReviewState::Brouillon,
        ]);

        $this->actingAs($subject)
            ->get(route('weekly-reviews.index'))
            ->assertInertia(fn (Assert $page): Assert => $page->where('canOpen', false)->where('subjects', []));
        $this->actingAs($subject)
            ->get(route('weekly-reviews.show', $review))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('permissions.submit', false)
                ->where('permissions.createPlan', false));
    }

    private function dailyService(): DailyReportService
    {
        return $this->app->make(DailyReportService::class);
    }

    /** @return array<string, mixed> */
    private function validData(): array
    {
        return [
            'planned_task' => 'Préparer le devis',
            'achieved_result' => 'Devis rédigé',
            'evidence_link' => 'https://example.test/preuve',
            'blocker_present' => false,
            'blocker_details' => null,
            'next_action' => 'Envoyer le devis',
            'help_requested' => false,
            'help_details' => null,
            'lateness_explanation' => null,
            'correction_reason' => null,
            'idempotency_key' => 'f4a1c2de-0000-4000-8000-000000000001',
        ];
    }
}
