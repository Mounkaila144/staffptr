<?php

namespace App\Services\Work;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Notifications\ObjectiveDeadlineNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * « Objectif proche de l'échéance » (FR31, AC 32, AC 35).
 *
 * Le service vit dans `Work` parce que c'est ce module qui possède l'objectif ; il lit le compte
 * destinataire dans `Identity` sans jamais y écrire (règle de couplage de `source-tree.md`).
 *
 * L'écriture `database` fait foi et précède la mise en file WhatsApp : si la file est en panne, la
 * notification existe quand même dans l'application (architecture § 9.4).
 */
final readonly class ObjectiveDeadlineReminderService
{
    private const TIMEZONE = 'Africa/Niamey';

    /** Fenêtre d'alerte : les objectifs dont l'échéance tombe dans les trois jours civils. */
    public const WARNING_DAYS = 3;

    public function dispatchDue(?CarbonImmutable $now = null): int
    {
        $today = ($now ?? CarbonImmutable::now('UTC'))->setTimezone(self::TIMEZONE)->startOfDay();
        $reminderDate = $today->toDateString();
        $sent = 0;

        $objectives = Objective::query()
            ->whereIn('state', ObjectiveDeadlineNotification::openStates())
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [
                $reminderDate,
                $today->addDays(self::WARNING_DAYS)->toDateString(),
            ])
            ->with('owner')
            ->orderBy('id')
            ->get();

        foreach ($objectives as $objective) {
            $owner = $objective->owner;

            // Une notification ne part jamais vers un compte qui n'est plus actif.
            if ($owner->state !== UserState::Actif) {
                continue;
            }

            $daysLeft = (int) $today->diffInDays($objective->due_date->startOfDay(), absolute: false);

            if ($this->dispatchOne($objective, $owner, $daysLeft, $reminderDate)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function dispatchOne(Objective $objective, User $owner, int $daysLeft, string $reminderDate): bool
    {
        $notificationId = ObjectiveDeadlineNotification::stableId(
            (int) $objective->getKey(),
            (int) $owner->getKey(),
            $reminderDate,
        );

        $claimed = DB::connection($owner->getConnectionName())->transaction(
            function () use ($objective, $owner, $daysLeft, $reminderDate, $notificationId): bool {
                if (DatabaseNotification::query()->whereKey($notificationId)->exists()) {
                    return false;
                }

                Notification::sendNow(
                    $owner,
                    ObjectiveDeadlineNotification::forDatabase($objective, (int) $owner->getKey(), $daysLeft, $reminderDate),
                    ['database'],
                );

                return true;
            }
        );

        if (! $claimed) {
            return false;
        }

        $owner->notify(ObjectiveDeadlineNotification::forWhatsApp(
            $objective,
            (int) $owner->getKey(),
            $daysLeft,
            $reminderDate,
        ));

        return true;
    }
}
