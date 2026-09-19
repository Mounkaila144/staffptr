<?php

namespace App\Services\Accountability;

use App\Enums\BlockerUrgency;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\Internship;
use App\Models\Accountability\SupportRequestBatch;
use App\Models\Accountability\SupportRequestBatchItem;
use App\Models\Accountability\TutorSupportSlot;
use App\Models\Identity\User;
use App\Notifications\GroupedSupportRequestNotification;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Regroupement des demandes non urgentes d'un stagiaire vers son tuteur (AC 34 à 39).
 *
 * Trois garanties tiennent l'ensemble :
 * - un blocage **urgent** n'entre jamais dans un lot et suit le chemin immédiat existant (AC 36) ;
 * - un tuteur **sans créneau** reçoit ses demandes à l'unité : le regroupement n'est jamais une
 *   cause de perte (AC 39) ;
 * - chaque demande reste un **objet distinct** dans la notification groupée, l'unicité de
 *   `blocker_id` interdisant structurellement fusion et doublon (AC 38).
 */
final class SupportRequestGroupingService
{
    private const TIMEZONE = 'Africa/Niamey';

    /**
     * Une demande est regroupée lorsqu'elle n'est pas urgente, qu'elle émane d'un stagiaire et
     * que son tuteur a configuré au moins un créneau.
     */
    public function shouldGroup(Blocker $blocker): bool
    {
        if ($blocker->urgency === BlockerUrgency::Urgente) {
            return false;
        }

        $isInternOfSolicited = Internship::query()
            ->where('user_id', $blocker->created_by)
            ->where('tutor_id', $blocker->solicited_user_id)
            ->occupyingTutorSlot()
            ->exists();

        if (! $isInternOfSolicited) {
            return false;
        }

        return TutorSupportSlot::query()->where('tutor_id', $blocker->solicited_user_id)->exists();
    }

    /**
     * Prochain créneau du tuteur, strictement postérieur à l'instant fourni, exprimé en UTC.
     * Les créneaux sont déclarés en jour et heure civils de Niamey (AC 34).
     */
    public function nextSlotFor(User $tutor, ?CarbonImmutable $from = null): ?CarbonImmutable
    {
        $slots = TutorSupportSlot::query()->forTutor($tutor)->get();

        if ($slots->isEmpty()) {
            return null;
        }

        $reference = ($from ?? CarbonImmutable::now('UTC'))->setTimezone(self::TIMEZONE);
        $candidates = [];

        foreach ($slots as $slot) {
            // On explore la semaine courante et la suivante pour couvrir le passage de dimanche.
            foreach ([0, 7] as $weekOffset) {
                $day = $reference->startOfWeek()->addDays($slot->weekday - 1 + $weekOffset);
                [$hour, $minute] = array_map('intval', explode(':', substr($slot->start_time, 0, 5)));
                $candidate = $day->setTime($hour, $minute);

                if ($candidate->greaterThan($reference)) {
                    $candidates[] = $candidate;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (CarbonImmutable $a, CarbonImmutable $b): int => $a <=> $b);

        return $candidates[0]->utc();
    }

    /**
     * Range la demande dans le lot du prochain créneau. Retourne `null` lorsque la demande ne
     * relève pas du regroupement — l'appelant conserve alors le chemin immédiat.
     */
    public function enqueue(Blocker $blocker, ?CarbonImmutable $now = null): ?SupportRequestBatch
    {
        if (! $this->shouldGroup($blocker)) {
            return null;
        }

        $tutor = User::query()->findOrFail($blocker->solicited_user_id);
        $slot = $this->nextSlotFor($tutor, $now);

        if (! $slot instanceof CarbonImmutable) {
            return null;
        }

        return DB::connection($blocker->getConnectionName())->transaction(function () use ($blocker, $tutor, $slot): SupportRequestBatch {
            $batch = SupportRequestBatch::query()
                ->where('tutor_id', $tutor->getKey())
                ->where('scheduled_for', $slot)
                ->lockForUpdate()
                ->first();

            if (! $batch instanceof SupportRequestBatch) {
                $batch = new SupportRequestBatch;
                $batch->fill([
                    'tutor_id' => $tutor->getKey(),
                    'scheduled_for' => $slot,
                    'idempotency_key' => (string) Str::uuid(),
                ]);
                $batch->saveOrFail();
            }

            // L'unicité de `blocker_id` fait le reste : une demande n'appartient qu'à un lot.
            $alreadyQueued = SupportRequestBatchItem::query()
                ->where('blocker_id', $blocker->getKey())
                ->exists();

            if (! $alreadyQueued) {
                $item = new SupportRequestBatchItem;
                $item->fill([
                    'support_request_batch_id' => $batch->getKey(),
                    'blocker_id' => $blocker->getKey(),
                ]);
                $item->saveOrFail();
            }

            return $batch;
        });
    }

    /**
     * Émet les lots dont le créneau est atteint, en une seule notification par lot.
     *
     * L'émission est idempotente : `delivered_at` est posé dans la même transaction que la
     * lecture verrouillée du lot, si bien qu'une exécution planifiée rejouée n'envoie rien deux
     * fois (architecture § 12.4).
     *
     * @return int Nombre de lots émis.
     */
    public function deliverDue(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now('UTC');

        $dueIds = SupportRequestBatch::query()
            ->whereNull('delivered_at')
            ->where('scheduled_for', '<=', $now)
            ->orderBy('scheduled_for')
            ->pluck('id');

        $delivered = 0;

        foreach ($dueIds as $batchId) {
            $batch = DB::connection((new SupportRequestBatch)->getConnectionName())->transaction(function () use ($batchId): ?SupportRequestBatch {
                $locked = SupportRequestBatch::query()->whereKey($batchId)->lockForUpdate()->first();

                if (! $locked instanceof SupportRequestBatch || $locked->isDelivered()) {
                    return null;
                }

                if ($locked->items()->doesntExist()) {
                    return null;
                }

                $locked->delivered_at = CarbonImmutable::now('UTC');
                $locked->saveOrFail();

                return $locked;
            });

            if (! $batch instanceof SupportRequestBatch) {
                continue;
            }

            $tutor = $batch->tutor;
            $requestIds = $batch->items()->pluck('blocker_id')->map(static fn (mixed $id): int => (int) $id)->all();

            Notification::sendNow(
                $tutor,
                GroupedSupportRequestNotification::forDatabase($batch, $requestIds),
                ['database'],
            );
            $tutor->notify(GroupedSupportRequestNotification::forWhatsApp($batch, $requestIds));

            $delivered++;
        }

        return $delivered;
    }

    /**
     * Ce que voit le stagiaire : à quel moment sa demande sera examinée, pour qu'il n'ait pas à
     * relancer (AC 37).
     *
     * @return list<array{blocker_id: int, problem: string, scheduled_for: string, delivered: bool}>
     */
    public function pendingFor(User $intern): array
    {
        return SupportRequestBatchItem::query()
            ->whereHas('blocker', fn ($query) => $query->where('created_by', $intern->getKey()))
            ->with(['blocker', 'batch'])
            ->get()
            ->map(fn (SupportRequestBatchItem $item): array => [
                'blocker_id' => (int) $item->blocker_id,
                'problem' => $item->blocker->problem,
                'scheduled_for' => DateTimeFormatter::format($item->batch->scheduled_for),
                'delivered' => $item->batch->isDelivered(),
            ])
            ->values()
            ->all();
    }

    /**
     * Créneaux déclarés par un tuteur, pour affichage.
     *
     * @return list<array{id: int, weekday: int, weekday_label: string, start_time: string}>
     */
    public function slotsFor(User $tutor): array
    {
        $days = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

        return TutorSupportSlot::query()
            ->forTutor($tutor)
            ->get()
            ->map(fn (TutorSupportSlot $slot): array => [
                'id' => (int) $slot->getKey(),
                'weekday' => $slot->weekday,
                'weekday_label' => $days[$slot->weekday] ?? '',
                'start_time' => substr($slot->start_time, 0, 5),
            ])
            ->values()
            ->all();
    }
}
