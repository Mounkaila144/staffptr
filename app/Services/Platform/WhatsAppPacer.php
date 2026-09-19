<?php

namespace App\Services\Platform;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Étalement des envois WhatsApp.
 *
 * Le problème n'est pas le volume quotidien, c'est sa **forme**. Le rappel de
 * rapport quotidien parcourt les destinataires en une boucle : à cinquante
 * personnes, cinquante messages quasi identiques partent dans la même minute.
 * Pour WhatsApp — dont l'application n'est cliente que par un canal non
 * officiel — cette signature est celle d'un émetteur de masse.
 *
 * Le calage se fait **à la mise en file**, jamais à l'envoi : retenir un worker
 * le temps d'un intervalle immobiliserait la file entière, y compris les
 * notifications internes qui, elles, doivent rester instantanées.
 *
 * Le principe est un seau percé : le cache retient l'instant du prochain
 * créneau libre. Chaque envoi le réserve et repousse le suivant d'un intervalle.
 * Deux workers concurrents ne peuvent pas réserver le même créneau — le verrou
 * s'en charge.
 */
class WhatsAppPacer
{
    private const SLOT_KEY = 'whatsapp:pace:next-slot';

    private const LOCK_KEY = 'whatsapp:pace:lock';

    private const LOCK_WAIT_SECONDS = 3;

    /**
     * Réserve un créneau et renvoie le délai à observer, en secondes.
     */
    public function reserveDelay(): int
    {
        $interval = $this->interval();
        $now = Carbon::now()->getTimestamp();

        $lock = Cache::lock(self::LOCK_KEY, 10);

        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            // Le verrou n'est pas obtenu : on envoie sans attendre. Une
            // notification livrée trop tôt reste une notification livrée ;
            // l'échec du calage ne doit jamais en faire perdre une.
            return 0;
        }

        try {
            $earliest = (int) Cache::get(self::SLOT_KEY, 0);
            $sendAt = max($now, $earliest);

            // La fenêtre est volontairement plus longue que le délai maximal :
            // elle doit survivre à la rafale qu'elle sert à étaler.
            Cache::put(self::SLOT_KEY, $sendAt + $interval, Carbon::now()->addHours(2));

            $delay = $sendAt - $now;
        } finally {
            $lock->release();
        }

        return max(0, min($delay, $this->maxDelay()));
    }

    /** Intervalle entre deux envois, en secondes. */
    private function interval(): int
    {
        $perMinute = (int) config('notifications.whatsapp.pace.per_minute', 4);

        return $perMinute > 0 ? max(1, intdiv(60, $perMinute)) : 0;
    }

    private function maxDelay(): int
    {
        return max(0, (int) config('notifications.whatsapp.pace.max_delay_seconds', 1800));
    }
}
