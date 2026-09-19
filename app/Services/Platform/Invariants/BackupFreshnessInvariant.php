<?php

namespace App\Services\Platform\Invariants;

use App\Services\Platform\BackupStatus;

/**
 * Fraîcheur de la dernière sauvegarde : moins de 26 heures (story 11.1 AC 11, story 10.1 AC 20,
 * NFR24).
 *
 * La story 10.1 avait posé ce contrôle en état `pending`, faute du contrat de sauvegarde. La
 * story 11.1 le livre : le contrôle est désormais **effectif** dès que le disque `backups` existe.
 *
 * Les 26 heures laissent deux heures de marge sur une sauvegarde quotidienne de 02 h 00 : un
 * décalage d'ordonnanceur ne doit pas déclencher une fausse alerte, mais une exécution manquée
 * doit se voir dès le lendemain.
 */
class BackupFreshnessInvariant implements InvariantCheck
{
    private const NAME = 'Fraîcheur de la dernière sauvegarde';

    public function __construct(private readonly BackupStatus $backupStatus) {}

    public function check(): InvariantResult
    {
        $expected = 'une sauvegarde de moins de '.BackupStatus::MAX_AGE_HOURS.' heures';

        if (! is_array(config('filesystems.disks.backups'))) {
            return InvariantResult::pending(
                self::NAME,
                'disque de sauvegarde non configuré sur cet environnement',
                $expected,
            );
        }

        $age = $this->backupStatus->ageInHours();

        if ($age === null) {
            return InvariantResult::fail(self::NAME, 'aucune sauvegarde présente', $expected);
        }

        $observed = "dernière sauvegarde il y a {$age} h";

        return $age <= BackupStatus::MAX_AGE_HOURS
            ? InvariantResult::pass(self::NAME, $observed, $expected)
            : InvariantResult::fail(self::NAME, $observed, $expected);
    }
}
