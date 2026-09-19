<?php

namespace App\Services\Platform\Invariants;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Expose à la supervision les envois de notification retombés en échec de file.
 *
 * Les notifications `database` sont écrites dans le cycle de la requête et font foi ; les envois
 * WhatsApp partent en file. Un échec de file est donc silencieux pour l'utilisateur : c'est
 * précisément ce que cet invariant rend visible (`ptr:check-invariants`).
 */
class QueuedNotificationFailureInvariant implements InvariantCheck
{
    private const NAME = 'Envois de notification en échec de file';

    public function check(): InvariantResult
    {
        try {
            $count = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return InvariantResult::fail(
                self::NAME,
                'table failed_jobs illisible',
                'aucun envoi en échec',
            );
        }

        $observed = $count === 0
            ? 'aucun envoi en échec'
            : "{$count} envoi".($count === 1 ? '' : 's').' en échec de file';

        return $count === 0
            ? InvariantResult::pass(self::NAME, $observed, 'aucun envoi en échec')
            : InvariantResult::fail(self::NAME, $observed, 'aucun envoi en échec');
    }
}
