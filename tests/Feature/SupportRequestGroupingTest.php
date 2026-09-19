<?php

namespace Tests\Feature;

use App\Enums\BlockerUrgency;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\Internship;
use App\Models\Accountability\SupportRequestBatch;
use App\Models\Accountability\SupportRequestBatchItem;
use App\Models\Accountability\TutorSupportSlot;
use App\Models\Identity\User;
use App\Models\Work\Task;
use App\Notifications\BlockerNotification;
use App\Notifications\GroupedSupportRequestNotification;
use App\Services\Accountability\BlockerService;
use App\Services\Accountability\SupportRequestGroupingService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Task 7 — créneaux de suivi et regroupement sans perte (AC 34 à 39, 44).
 */
class SupportRequestGroupingTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Queue::fake();
        // Lundi 10 août 2026, 8 h à Niamey.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-10 08:00:00', 'Africa/Niamey'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * AC 34 : les créneaux sont paramétrables par tuteur, en jours et heures civils de Niamey.
     */
    public function test_ac_34_the_next_slot_is_computed_from_the_tutor_declaration(): void
    {
        [$tutor] = $this->tutorAndIntern();
        TutorSupportSlot::factory()->on(2, '10:00:00')->create(['tutor_id' => $tutor->getKey()]);
        TutorSupportSlot::factory()->on(4, '15:30:00')->create(['tutor_id' => $tutor->getKey()]);

        $next = $this->grouping()->nextSlotFor($tutor, CarbonImmutable::now('UTC'));

        $this->assertInstanceOf(CarbonImmutable::class, $next);
        // Mardi 11 août 2026 à 10 h, heure de Niamey.
        $this->assertSame('11/08/2026 10:00', $next->setTimezone('Africa/Niamey')->format('d/m/Y H:i'));

        // Après le mardi, c'est le jeudi qui vient.
        $afterTuesday = $this->grouping()->nextSlotFor(
            $tutor,
            CarbonImmutable::parse('2026-08-11 12:00:00', 'Africa/Niamey'),
        );
        $this->assertSame('13/08/2026 15:30', $afterTuesday?->setTimezone('Africa/Niamey')->format('d/m/Y H:i'));
    }

    /**
     * AC 36 et 44 : le contraste des deux chemins. Un blocage urgent notifie immédiatement et
     * n'entre dans aucun lot ; un blocage ordinaire entre dans le lot et ne notifie pas encore.
     */
    public function test_ac_36_and_44_urgent_escapes_grouping_while_ordinary_enters_it(): void
    {
        [$tutor, $intern] = $this->tutorAndIntern();
        TutorSupportSlot::factory()->on(2, '10:00:00')->create(['tutor_id' => $tutor->getKey()]);

        $urgent = $this->createBlocker($intern, $tutor, BlockerUrgency::Urgente);

        // Chemin immédiat : une notification, aucun lot.
        $this->assertSame(1, DatabaseNotification::query()->where('type', BlockerNotification::class)->count());
        $this->assertSame(0, SupportRequestBatchItem::query()->where('blocker_id', $urgent->getKey())->count());

        $ordinary = $this->createBlocker($intern, $tutor, BlockerUrgency::Normale);

        // Chemin groupé : aucune notification supplémentaire, une entrée de lot.
        $this->assertSame(1, DatabaseNotification::query()->where('type', BlockerNotification::class)->count());
        $this->assertSame(1, SupportRequestBatchItem::query()->where('blocker_id', $ordinary->getKey())->count());
        $this->assertSame(0, DatabaseNotification::query()->where('type', GroupedSupportRequestNotification::class)->count());
    }

    /**
     * AC 39 : un tuteur sans créneau reçoit ses demandes à l'unité — le regroupement n'est jamais
     * une cause de perte.
     */
    public function test_ac_39_a_tutor_without_any_slot_receives_requests_one_by_one(): void
    {
        [$tutor, $intern] = $this->tutorAndIntern();

        $this->createBlocker($intern, $tutor, BlockerUrgency::Normale);
        $this->createBlocker($intern, $tutor, BlockerUrgency::Normale);

        $this->assertSame(2, DatabaseNotification::query()->where('type', BlockerNotification::class)->count());
        $this->assertSame(0, SupportRequestBatch::query()->count());
    }

    /**
     * AC 38 : cinq demandes, cinq objets distincts dans une seule notification. Aucune n'est
     * perdue ni fusionnée.
     */
    public function test_ac_38_five_requests_stay_five_distinct_objects_in_one_notification(): void
    {
        [$tutor, $intern] = $this->tutorAndIntern();
        TutorSupportSlot::factory()->on(2, '10:00:00')->create(['tutor_id' => $tutor->getKey()]);

        $created = [];
        for ($index = 0; $index < 5; $index++) {
            $created[] = (int) $this->createBlocker($intern, $tutor, BlockerUrgency::Normale)->getKey();
        }

        $this->assertSame(1, SupportRequestBatch::query()->count());
        $this->assertSame(5, SupportRequestBatchItem::query()->count());

        // Le créneau est atteint : une seule notification part.
        $delivered = $this->grouping()->deliverDue(
            CarbonImmutable::parse('2026-08-11 10:05:00', 'Africa/Niamey')->utc(),
        );

        $this->assertSame(1, $delivered);

        $notifications = DatabaseNotification::query()
            ->where('type', GroupedSupportRequestNotification::class)
            ->get();

        $this->assertCount(1, $notifications);
        $payload = $notifications->firstOrFail()->data;
        $this->assertSame(5, $payload['request_count']);
        $this->assertEqualsCanonicalizing($created, $payload['request_ids']);
        // Cinq identifiants distincts : rien n'a été fusionné.
        $this->assertCount(5, array_unique($payload['request_ids']));
    }

    /**
     * AC 35 : les demandes sont présentées au créneau suivant, pas avant.
     */
    public function test_ac_35_nothing_is_delivered_before_the_slot(): void
    {
        [$tutor, $intern] = $this->tutorAndIntern();
        TutorSupportSlot::factory()->on(2, '10:00:00')->create(['tutor_id' => $tutor->getKey()]);
        $this->createBlocker($intern, $tutor, BlockerUrgency::Normale);

        $before = $this->grouping()->deliverDue(
            CarbonImmutable::parse('2026-08-11 09:00:00', 'Africa/Niamey')->utc(),
        );

        $this->assertSame(0, $before);
        $this->assertSame(0, DatabaseNotification::query()->where('type', GroupedSupportRequestNotification::class)->count());

        $after = $this->grouping()->deliverDue(
            CarbonImmutable::parse('2026-08-11 10:00:01', 'Africa/Niamey')->utc(),
        );

        $this->assertSame(1, $after);
    }

    /**
     * L'émission planifiée est idempotente : rejouée, elle n'envoie rien deux fois.
     */
    public function test_the_scheduled_delivery_is_idempotent(): void
    {
        [$tutor, $intern] = $this->tutorAndIntern();
        TutorSupportSlot::factory()->on(2, '10:00:00')->create(['tutor_id' => $tutor->getKey()]);
        $this->createBlocker($intern, $tutor, BlockerUrgency::Normale);
        $slotReached = CarbonImmutable::parse('2026-08-11 10:05:00', 'Africa/Niamey')->utc();

        $this->assertSame(1, $this->grouping()->deliverDue($slotReached));
        $this->assertSame(0, $this->grouping()->deliverDue($slotReached));
        $this->assertSame(0, $this->grouping()->deliverDue($slotReached->addHour()));

        $this->assertSame(1, DatabaseNotification::query()->where('type', GroupedSupportRequestNotification::class)->count());
        $this->assertNotNull(SupportRequestBatch::query()->firstOrFail()->delivered_at);
    }

    /**
     * AC 37 : le stagiaire voit à quel moment sa demande sera examinée.
     */
    public function test_ac_37_the_intern_sees_when_their_request_will_be_examined(): void
    {
        [$tutor, $intern] = $this->tutorAndIntern();
        TutorSupportSlot::factory()->on(2, '10:00:00')->create(['tutor_id' => $tutor->getKey()]);
        $blocker = $this->createBlocker($intern, $tutor, BlockerUrgency::Normale);

        $pending = $this->grouping()->pendingFor($intern);

        $this->assertCount(1, $pending);
        $this->assertSame((int) $blocker->getKey(), $pending[0]['blocker_id']);
        $this->assertSame('11/08/2026 10:00', $pending[0]['scheduled_for']);
        $this->assertFalse($pending[0]['delivered']);

        $this->grouping()->deliverDue(CarbonImmutable::parse('2026-08-11 10:05:00', 'Africa/Niamey')->utc());

        $this->assertTrue($this->grouping()->pendingFor($intern)[0]['delivered']);
    }

    /**
     * Une demande d'un compte qui n'est pas stagiaire du tuteur suit le chemin immédiat.
     */
    public function test_a_request_from_someone_who_is_not_their_intern_is_never_grouped(): void
    {
        [$tutor] = $this->tutorAndIntern();
        TutorSupportSlot::factory()->on(2, '10:00:00')->create(['tutor_id' => $tutor->getKey()]);
        $colleague = User::factory()->active()->withRole('employe')->create();

        $this->createBlocker($colleague, $tutor, BlockerUrgency::Normale);

        $this->assertSame(1, DatabaseNotification::query()->where('type', BlockerNotification::class)->count());
        $this->assertSame(0, SupportRequestBatch::query()->count());
    }

    private function grouping(): SupportRequestGroupingService
    {
        return app(SupportRequestGroupingService::class);
    }

    /** @return array{0: User, 1: User} */
    private function tutorAndIntern(): array
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $intern = User::factory()->active()->withRole('stagiaire')->create();
        Internship::factory()->forTutor($tutor)->create(['user_id' => $intern->getKey()]);

        return [$tutor, $intern];
    }

    private function createBlocker(User $author, User $solicited, BlockerUrgency $urgency): Blocker
    {
        $task = Task::factory()->create(['assignee_id' => $author->getKey(), 'created_by' => $author->getKey()]);

        return app(BlockerService::class)->create([
            'origin_type' => 'task',
            'origin_id' => $task->getKey(),
            'problem' => 'Une information manque pour poursuivre.',
            'urgency' => $urgency,
            'solicited_user_id' => $solicited->getKey(),
            'reported_on' => '2026-08-10',
            'deadline_impact' => "Risque d'un jour.",
            'attempted_action' => 'Consultation des documents.',
        ], $author);
    }
}
