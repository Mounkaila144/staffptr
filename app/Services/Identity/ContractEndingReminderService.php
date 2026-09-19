<?php

namespace App\Services\Identity;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Notifications\ContractEndingNotification;
use App\Services\Platform\WhatsAppChannel;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Consommateur de {@see ContractEndingService} (AC 32, AC 33, FR31).
 *
 * La story 3.2 avait exposé la **détection** des fins de contrat et de stage, sans destinataire :
 * le centre de notifications n'existait pas encore à ce jalon. Ce service ferme la boucle. La
 * détection n'est pas réécrite ici — elle est appelée telle quelle, ce qui garantit que le délai
 * paramétré `contract_end_warning_days` reste l'unique source de vérité.
 *
 * Deux destinataires, pour deux raisons distinctes : la personne concernée, parce qu'une échéance
 * qui la concerne ne doit pas lui être cachée, et la direction, parce qu'elle seule peut
 * renouveler ou clore. Aucun effet n'est produit sur le compte : c'est une notification, pas une
 * fin de contrat automatique.
 */
final readonly class ContractEndingReminderService
{
    private const TIMEZONE = 'Africa/Niamey';

    public function __construct(private ContractEndingService $contractEndings) {}

    public function dispatchDue(?CarbonImmutable $now = null): int
    {
        $today = ($now ?? CarbonImmutable::now('UTC'))->setTimezone(self::TIMEZONE)->startOfDay();
        $reminderDate = $today->toDateString();
        $sent = 0;

        $directionUsers = User::query()
            ->role('direction')
            ->where('state', UserState::Actif)
            ->orderBy('id')
            ->get();

        foreach ($this->contractEndings->endingWithin() as $concerned) {
            $daysLeft = (int) $today->diffInDays($concerned->contract_end_date->startOfDay(), absolute: false);
            $recipients = $directionUsers->keyBy(fn (User $user): int => (int) $user->getKey());
            $recipients->put((int) $concerned->getKey(), $concerned);

            foreach ($recipients as $recipient) {
                if ($this->dispatchOne($recipient, $concerned, $daysLeft, $reminderDate)) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    private function dispatchOne(User $recipient, User $concerned, int $daysLeft, string $reminderDate): bool
    {
        $notificationId = ContractEndingNotification::stableId(
            (int) $recipient->getKey(),
            (int) $concerned->getKey(),
            $reminderDate,
        );

        $claimed = DB::connection($recipient->getConnectionName())->transaction(
            function () use ($recipient, $concerned, $daysLeft, $reminderDate, $notificationId): bool {
                if ($recipient->state !== UserState::Actif
                    || DatabaseNotification::query()->whereKey($notificationId)->exists()) {
                    return false;
                }

                Notification::sendNow(
                    $recipient,
                    ContractEndingNotification::build(
                        (int) $recipient->getKey(),
                        $concerned,
                        $daysLeft,
                        $reminderDate,
                        ['database'],
                    ),
                    ['database'],
                );

                return true;
            }
        );

        if (! $claimed) {
            return false;
        }

        $recipient->notify(ContractEndingNotification::build(
            (int) $recipient->getKey(),
            $concerned,
            $daysLeft,
            $reminderDate,
            [WhatsAppChannel::class],
        ));

        return true;
    }
}
