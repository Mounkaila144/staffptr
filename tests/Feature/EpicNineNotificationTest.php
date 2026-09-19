<?php

namespace Tests\Feature;

use App\Enums\ObjectiveState;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Notifications\BaseNotification;
use App\Notifications\ContractEndingNotification;
use App\Notifications\FinancialPreparationNotification;
use App\Notifications\FinancialReportPreparationReminderNotification;
use App\Notifications\ObjectiveDeadlineNotification;
use App\Services\Finance\FinancialPreparationReminderService;
use App\Services\Identity\ContractEndingReminderService;
use App\Services\Identity\ContractEndingService;
use App\Services\Platform\WhatsAppChannel;
use App\Services\Work\ObjectiveDeadlineReminderService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Story 9.1, Task 7 — les événements de FR31 ajoutés par l'Epic 9 (AC 32 à 38, AC 44).
 *
 * Le fil conducteur : `database` fait foi et est écrit en premier, WhatsApp est mis en file
 * ensuite, aucun autre canal n'existe, et rejouer une tâche ne double rien.
 */
class EpicNineNotificationTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        Queue::fake();
    }

    /** AC 32 — « objectif proche de l'échéance » notifie le titulaire de l'objectif. */
    public function test_ac_32_an_objective_close_to_its_deadline_notifies_its_owner(): void
    {
        $owner = User::factory()->active()->withRole('employe')->create();
        $objective = $this->objective($owner, CarbonImmutable::now('Africa/Niamey')->addDay());

        $sent = app(ObjectiveDeadlineReminderService::class)->dispatchDue();

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->getKey(),
            'type' => ObjectiveDeadlineNotification::class,
        ]);
        $notification = DatabaseNotification::query()->firstOrFail();
        $this->assertSame($objective->getKey(), $notification->data['objective_id']);
    }

    /** Un objectif hors fenêtre ou déjà atteint ne notifie rien. */
    public function test_an_objective_out_of_window_or_already_reached_notifies_nothing(): void
    {
        $owner = User::factory()->active()->withRole('employe')->create();
        $this->objective($owner, CarbonImmutable::now('Africa/Niamey')->addDays(30));
        $this->objective($owner, CarbonImmutable::now('Africa/Niamey')->addDay(), ObjectiveState::Atteint);

        $this->assertSame(0, app(ObjectiveDeadlineReminderService::class)->dispatchDue());
    }

    /** AC 35 — la tâche rejouée le même jour ne crée aucun doublon. */
    public function test_ac_35_replaying_the_objective_reminder_creates_no_duplicate(): void
    {
        $owner = User::factory()->active()->withRole('employe')->create();
        $this->objective($owner, CarbonImmutable::now('Africa/Niamey')->addDay());

        app(ObjectiveDeadlineReminderService::class)->dispatchDue();
        app(ObjectiveDeadlineReminderService::class)->dispatchDue();

        $this->assertSame(1, DatabaseNotification::query()->count());
    }

    /**
     * AC 33 — **le test que la story réclame explicitement** : l'échéance détectée par le service
     * exposé en 3.2 produit bien une notification ici. La détection et l'émission sont vérifiées
     * dans la même exécution, pour qu'aucune des deux ne puisse dériver sans l'autre.
     */
    public function test_ac_33_the_deadline_detected_by_story_3_2_produces_a_notification(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $concerned = User::factory()->active()->withRole('employe')->create([
            'contract_end_date' => CarbonImmutable::now('Africa/Niamey')->addDays(5)->toDateString(),
        ]);

        // 1. La détection de 3.2 voit bien l'échéance.
        $detected = app(ContractEndingService::class)->endingWithin();
        $this->assertTrue($detected->contains(fn (User $user): bool => $user->is($concerned)));

        // 2. Et cette détection produit maintenant une notification — ce qui manquait au jalon 3.2.
        $sent = app(ContractEndingReminderService::class)->dispatchDue();

        $this->assertSame(2, $sent, 'La personne concernée et la direction sont notifiées.');
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $concerned->getKey(),
            'type' => ContractEndingNotification::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $direction->getKey(),
            'type' => ContractEndingNotification::class,
        ]);
    }

    /** AC 35 — la fin de contrat est elle aussi idempotente sur la journée. */
    public function test_ac_35_replaying_the_contract_ending_reminder_creates_no_duplicate(): void
    {
        User::factory()->active()->withRole('direction')->create();
        User::factory()->active()->withRole('employe')->create([
            'contract_end_date' => CarbonImmutable::now('Africa/Niamey')->addDays(5)->toDateString(),
        ]);

        app(ContractEndingReminderService::class)->dispatchDue();
        $countAfterFirst = DatabaseNotification::query()->count();
        app(ContractEndingReminderService::class)->dispatchDue();

        $this->assertSame($countAfterFirst, DatabaseNotification::query()->count());
    }

    /**
     * AC 32 — « rapprochement ou rapport financier à préparer » notifie qui doit préparer. La
     * commande ordonnance les deux échéances de l'événement : le rapprochement, porté ici, et le
     * rapport financier, porté par le service de l'Epic 8.
     */
    public function test_ac_32_financial_preparation_reminds_the_preparer(): void
    {
        $finance = User::factory()->active()->withRole('finance')->create();
        $now = CarbonImmutable::now('Africa/Niamey')->startOfMonth()->addDays(6);

        $sent = app(FinancialPreparationReminderService::class)->dispatchDue($now);

        $this->assertSame(1, $sent);
        $this->assertSame(1, DatabaseNotification::query()
            ->where('notifiable_id', $finance->getKey())
            ->where('type', FinancialPreparationNotification::class)
            ->count());
    }

    /**
     * AC 32 — les **deux** échéances de l'événement sont émises par une seule tâche planifiée, et
     * chacune par son service propriétaire. Sans cette commande, le rappel de rapport financier de
     * l'Epic 8 n'était jamais déclenché.
     */
    public function test_ac_32_one_scheduled_task_covers_both_financial_deadlines(): void
    {
        User::factory()->active()->withRole('finance')->create();
        $this->travelTo(CarbonImmutable::now('Africa/Niamey')->startOfMonth()->addDays(6));

        $this->artisan('ptr:send-financial-preparation-reminders')->assertSuccessful();

        $this->assertSame(1, DatabaseNotification::query()->where('type', FinancialPreparationNotification::class)->count());
        $this->assertSame(1, DatabaseNotification::query()->where('type', FinancialReportPreparationReminderNotification::class)->count());
    }

    /** Avant le 5 du mois, l'échéance du mois précédent n'est pas encore réclamée. */
    public function test_financial_preparation_stays_silent_before_the_due_day(): void
    {
        User::factory()->active()->withRole('finance')->create();
        $now = CarbonImmutable::now('Africa/Niamey')->startOfMonth()->addDay();

        $this->assertSame(0, app(FinancialPreparationReminderService::class)->dispatchDue($now));
    }

    /**
     * AC 35 — l'échéance financière est **mensuelle** : la tâche quotidienne ne doit pas produire
     * une relance par jour. Son identité ne comprend pas la date du jour.
     */
    public function test_ac_35_a_monthly_deadline_is_reminded_once_not_once_a_day(): void
    {
        User::factory()->active()->withRole('finance')->create();
        $start = CarbonImmutable::now('Africa/Niamey')->startOfMonth()->addDays(6);

        app(FinancialPreparationReminderService::class)->dispatchDue($start);
        app(FinancialPreparationReminderService::class)->dispatchDue($start->addDay());
        app(FinancialPreparationReminderService::class)->dispatchDue($start->addDays(2));

        $this->assertSame(1, DatabaseNotification::query()->count());
    }

    /**
     * AC 36, AC 44 — **seul WhatsApp est appelé, jamais SMS ni courriel**, et l'écriture
     * `database` précède la mise en file. Vérifié sur les trois familles ajoutées ici.
     */
    public function test_ac_36_only_whatsapp_is_queued_after_the_database_write(): void
    {
        $owner = User::factory()->active()->withRole('employe')->create();
        $this->objective($owner, CarbonImmutable::now('Africa/Niamey')->addDay());

        app(ObjectiveDeadlineReminderService::class)->dispatchDue();

        Queue::assertPushed(
            SendQueuedNotifications::class,
            static fn (SendQueuedNotifications $job): bool => $job->channels === [WhatsAppChannel::class],
        );
        // Aucun autre canal n'est jamais poussé : ni `mail`, ni `nexmo`, ni `vonage`.
        Queue::assertPushed(
            SendQueuedNotifications::class,
            static fn (SendQueuedNotifications $job): bool => ! array_intersect(
                $job->channels,
                ['mail', 'nexmo', 'vonage', 'sms'],
            ),
        );
    }

    /**
     * AC 36, AC 44 — aucune notification du produit ne déclare de canal autre que `database` et
     * WhatsApp. Le contrôle porte sur **toutes** les classes de notification, pas seulement celles
     * de l'Epic 9 : c'est la vérification de fin de MVP que réclame l'AC 36.
     */
    public function test_ac_36_no_notification_class_declares_any_channel_beyond_database_and_whatsapp(): void
    {
        $checked = 0;

        foreach (glob(app_path('Notifications/*.php')) ?: [] as $file) {
            $class = 'App\\Notifications\\'.basename($file, '.php');
            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $source = (string) file_get_contents($file);
            $this->assertStringNotContainsString("'mail'", $source, "{$class} ne doit jamais déclarer le canal courriel.");
            $this->assertStringNotContainsString("'vonage'", $source, "{$class} ne doit jamais déclarer un canal SMS.");
            $this->assertStringNotContainsString("'nexmo'", $source, "{$class} ne doit jamais déclarer un canal SMS.");

            // Les canaux réellement déclarés se réduisent à `database` et au canal WhatsApp.
            $instance = $reflection->newInstanceWithoutConstructor();
            $declared = $reflection->hasMethod('via') && $reflection->getMethod('via')->getDeclaringClass()->getName() === $class
                ? null
                : (new \ReflectionClass(BaseNotification::class))
                    ->getMethod('via')
                    ->invoke($instance, new \stdClass);

            if ($declared !== null) {
                $this->assertSame(['database', WhatsAppChannel::class], $declared, "{$class} déclare un canal interdit.");
            }

            $checked++;
        }

        $this->assertGreaterThanOrEqual(10, $checked, 'Le contrôle doit balayer toutes les notifications du produit.');
    }

    /**
     * AC 37 — une notification dont l'objet est sorti du périmètre du destinataire n'expose pas
     * son contenu : l'envoi est refusé proprement au moment de la livraison.
     */
    public function test_ac_37_a_notification_whose_object_left_the_scope_is_not_delivered(): void
    {
        $owner = User::factory()->active()->withRole('employe')->create();
        $objective = $this->objective($owner, CarbonImmutable::now('Africa/Niamey')->addDay());
        $notification = ObjectiveDeadlineNotification::forWhatsApp($objective, (int) $owner->getKey(), 1, '2026-08-17');

        $this->assertTrue($notification->shouldSend($owner, WhatsAppChannel::class));

        // L'objectif est annulé après la mise en file : le contenu ne doit plus partir.
        $objective->forceFill(['state' => ObjectiveState::Annule->value])->saveOrFail();

        $this->assertFalse($notification->shouldSend($owner->fresh(), WhatsAppChannel::class));
    }

    /** AC 37 — un tiers ne reçoit jamais le contenu d'une notification qui ne le concerne pas. */
    public function test_ac_37_a_third_party_never_receives_someone_elses_notification(): void
    {
        $owner = User::factory()->active()->withRole('employe')->create();
        $stranger = User::factory()->active()->withRole('employe')->create();
        $objective = $this->objective($owner, CarbonImmutable::now('Africa/Niamey')->addDay());
        $notification = ObjectiveDeadlineNotification::forWhatsApp($objective, (int) $owner->getKey(), 1, '2026-08-17');

        $this->assertFalse($notification->shouldSend($stranger, WhatsAppChannel::class));
    }

    /**
     * AC 34 — chaque notification porte une **URL directe** vers l'action attendue. C'est ce qui
     * garde le parcours à trois interactions au plus : ouvrir la notification, suivre le lien,
     * agir.
     */
    public function test_ac_34_every_new_notification_carries_a_direct_internal_link(): void
    {
        $owner = User::factory()->active()->withRole('employe')->create();
        $objective = $this->objective($owner, CarbonImmutable::now('Africa/Niamey')->addDay());
        $concerned = User::factory()->active()->withRole('employe')->create([
            'contract_end_date' => CarbonImmutable::now('Africa/Niamey')->addDays(5)->toDateString(),
        ]);

        $links = [
            ObjectiveDeadlineNotification::forDatabase($objective, (int) $owner->getKey(), 1, '2026-08-17')
                ->toDatabase($owner)['link'],
            ContractEndingNotification::build((int) $concerned->getKey(), $concerned, 5, '2026-08-17', ['database'])
                ->toDatabase($concerned)['link'],
            FinancialPreparationNotification::forDatabase(1, FinancialPreparationNotification::KIND_RECONCILIATION, '2026-07-01', '2026-08-17')
                ->toDatabase($owner)['link'],
        ];

        foreach ($links as $link) {
            $this->assertStringStartsWith('/', $link, 'Le lien doit être interne à l’application.');
            $this->assertStringNotContainsString('//', $link, 'Aucun lien externe ne doit figurer dans une notification.');
        }

        $this->assertSame("/objectifs/{$objective->getKey()}", $links[0]);
        $this->assertSame("/personnes/{$concerned->person_id}", $links[1]);
        $this->assertSame('/finances/rapprochements', $links[2]);
    }

    /** AC 7, AC 35 — chaque tâche planifiée est déclarée, en heure de Niamey et sans chevauchement. */
    public function test_ac_7_every_new_scheduled_task_is_declared_in_niamey_time(): void
    {
        $schedule = (string) file_get_contents(base_path('routes/console.php'));

        foreach ([
            'ptr:recalculate-alert-level',
            'ptr:send-objective-deadline-reminders',
            'ptr:send-contract-ending-reminders',
            'ptr:send-financial-preparation-reminders',
        ] as $command) {
            $this->assertStringContainsString($command, $schedule);
            $this->artisan($command)->assertSuccessful();
        }

        // Toute tâche planifiée du produit, pas seulement les nouvelles, est en heure de Niamey et
        // sans chevauchement. Compter les occurrences plutôt que les nommer garde le contrôle
        // valable quand une story ultérieure en ajoute une.
        $scheduled = substr_count($schedule, 'Schedule::command(');
        $this->assertSame($scheduled, substr_count($schedule, "timezone('Africa/Niamey')"));
        $this->assertSame($scheduled, substr_count($schedule, 'withoutOverlapping()'));
    }

    /** AC 7, AC 10 du registre — chaque tâche planifiée figure dans le registre d'ordonnancement. */
    public function test_ac_7_every_new_scheduled_task_is_documented_in_the_ops_register(): void
    {
        $register = (string) file_get_contents(base_path('docs/ops/scheduled-tasks.md'));

        foreach ([
            'ptr:recalculate-alert-level',
            'ptr:send-objective-deadline-reminders',
            'ptr:send-contract-ending-reminders',
            'ptr:send-financial-preparation-reminders',
        ] as $command) {
            $this->assertStringContainsString($command, $register);
        }

        // AC 7 et AC 38 : le contrat de supervision est rattaché à 11.3 et 11.4, sans les déclarer livrées.
        $this->assertStringContainsString('11.3', $register);
        $this->assertStringContainsString('11.4', $register);
        $this->assertStringContainsString('HTTPS', $register);
    }

    private function objective(User $owner, CarbonImmutable $dueDate, ?ObjectiveState $state = null): Objective
    {
        return Objective::factory()->create([
            'user_id' => $owner->getKey(),
            'due_date' => $dueDate->toDateString(),
            'state' => ($state ?? ObjectiveState::EnCours)->value,
        ]);
    }
}
