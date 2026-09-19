<?php

namespace App\Services\Finance;

use App\Enums\AlertLevel;
use App\Enums\UserState;
use App\Models\Identity\User;
use App\Notifications\CorrectionPlanReminderNotification;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Relance de `direction` en niveau orange, jusqu'à ce qu'un plan correctif existe (AC 11, AC 16).
 *
 * Trois propriétés sont recherchées.
 *
 * - **Bornée.** La relance est une notification, rien d'autre. Elle ne suspend aucun compte, ne
 *   retire aucune permission, ne bloque aucune écriture : le niveau d'alerte n'agit jamais sur une
 *   personne (AC 12, RM-18).
 * - **Idempotente.** L'identité de la notification est `destinataire + mois + date civile` ; la
 *   tâche rejouée le même jour ne recrée rien (AC 35, AC 44).
 * - **Auto-extinctive.** Dès qu'un plan est enregistré pour le mois, plus aucune relance n'est
 *   émise, et celles déjà en file s'annulent d'elles-mêmes (AC 16).
 */
final readonly class CorrectionPlanReminderService
{
    private const TIMEZONE = 'Africa/Niamey';

    public function __construct(
        private AlertLevelService $alertLevelService,
        private CorrectionPlanService $correctionPlans,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Émet les relances dues et retourne leur nombre. Zéro dès que le niveau n'est plus orange ou
     * qu'un plan existe.
     */
    public function dispatchDue(?CarbonImmutable $now = null): int
    {
        $localNow = ($now ?? CarbonImmutable::now('UTC'))->setTimezone(self::TIMEZONE);
        $assessment = $this->alertLevelService->assess($localNow);

        if ($assessment->level !== AlertLevel::Orange) {
            return 0;
        }

        $month = $assessment->month->toDateString();

        if ($this->correctionPlans->existsFor($month)) {
            return 0;
        }

        $dueLabel = $this->dueLabel($month, $localNow);
        $sent = 0;

        foreach ($this->recipients() as $recipient) {
            if ($this->dispatchOne($recipient, $month, $localNow->toDateString(), $dueLabel)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Comptes `direction` actifs. La relance vise le rôle qui doit produire le plan, pas une
     * personne nommée en dur.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return User::query()
            ->role('direction')
            ->where('state', UserState::Actif)
            ->with('person')
            ->orderBy('id')
            ->get();
    }

    /**
     * Échéance des 48 heures, comptée depuis le passage effectif en orange. Tant que la tâche
     * planifiée n'a jamais tourné, le délai part de maintenant.
     */
    public function dueAt(string $month, CarbonImmutable $localNow): CarbonImmutable
    {
        $state = $this->alertLevelService->state($month);
        $dueAt = $state?->correctionPlanDueAt();

        return ($dueAt ?? $localNow->addHours(48))->setTimezone(self::TIMEZONE);
    }

    private function dueLabel(string $month, CarbonImmutable $localNow): string
    {
        $dueAt = $this->dueAt($month, $localNow);

        if ($dueAt->lessThanOrEqualTo($localNow)) {
            return 'le délai de 48 heures est dépassé';
        }

        return 'à enregistrer avant le '.$dueAt->format('d/m/Y à H\hi');
    }

    private function dispatchOne(User $recipient, string $month, string $date, string $dueLabel): bool
    {
        $notificationId = CorrectionPlanReminderNotification::stableId(
            (int) $recipient->getKey(),
            $month,
            $date,
        );

        $claimed = DB::connection($recipient->getConnectionName())->transaction(
            function () use ($recipient, $month, $date, $dueLabel, $notificationId): bool {
                $locked = User::query()->whereKey($recipient->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->state !== UserState::Actif
                    || ! $locked->hasRole('direction')
                    || $this->correctionPlans->existsFor($month)
                    || DatabaseNotification::query()->whereKey($notificationId)->exists()) {
                    return false;
                }

                Notification::sendNow(
                    $locked,
                    CorrectionPlanReminderNotification::forDatabase(
                        (int) $locked->getKey(),
                        $month,
                        $date,
                        $dueLabel,
                    ),
                    ['database'],
                );

                // AC 13 : l'effet du niveau d'alerte est tracé, en nommant le niveau et l'objet
                // concerné, dans la transaction qui l'a produit (SOC-02).
                $this->auditLogger->record(
                    actorId: null,
                    actorLabel: 'Tâche planifiée',
                    auditable: $locked,
                    action: 'correction_plan_reminder_sent',
                    newValues: [
                        'alert_level' => AlertLevel::Orange->value,
                        'month' => $month,
                        'reminder_date' => $date,
                    ],
                    reason: "Relance du plan correctif exigé par le niveau d'alerte orange.",
                );

                return true;
            }
        );

        if (! $claimed) {
            return false;
        }

        $recipient->notify(CorrectionPlanReminderNotification::forWhatsApp(
            (int) $recipient->getKey(),
            $month,
            $date,
            $dueLabel,
        ));

        return true;
    }
}
