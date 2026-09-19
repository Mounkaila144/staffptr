<?php

namespace App\Console\Commands;

use App\Services\Platform\RestoreLog;
use App\Services\Platform\RestoreTestService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Test mensuel de restauration (story 11.1, AC 13 à 19, AC 55, NFR25).
 *
 * La commande récupère la dernière archive, la restaure dans une base **jetable**, vérifie les
 * invariants compatibles avec les tables présentes, détruit la base et consigne le résultat daté
 * dans le registre persistant.
 *
 * **Elle échoue bruyamment** (AC 17, AC 19) : archive corrompue, clé invalide, stockage
 * injoignable ou dump illisible produisent un code d'erreur et une entrée d'échec au registre.
 * Une commande qui se tairait laisserait croire à une protection inexistante.
 */
#[Signature('ptr:test-restore')]
#[Description('Restaure la dernière sauvegarde dans une base jetable et consigne le résultat')]
class TestRestore extends Command
{
    public function __construct(
        private readonly RestoreTestService $restoreTest,
        private readonly RestoreLog $restoreLog,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->info('Test de restauration en base jetable…');

        try {
            $report = $this->restoreTest->run();
        } catch (Throwable $exception) {
            // Le registre est écrit **avant** de rendre la main : un échec non consigné serait un
            // échec oublié.
            $this->restoreLog->append(false, $exception->getMessage());

            Log::channel(config('logging.default'))->error('Test de restauration en échec.', [
                'reason' => $exception->getMessage(),
            ]);

            $this->components->error("Test de restauration en échec : {$exception->getMessage()}");
            $this->components->warn('Le registre a été mis à jour. Une alerte d’exploitation est attendue.');

            return self::FAILURE;
        }

        foreach ($report['checks'] as $check) {
            $this->components->info("Contrôle — {$check['name']} : {$check['rows']} ligne(s).");
        }

        $this->restoreLog->append(
            true,
            "Restauration vérifiée depuis « {$report['archive']} ».",
            [
                'durée' => $report['duration_seconds'].' s',
                'tables' => $report['tables'],
                'origine' => $report['disk'],
            ],
        );

        $this->components->info(sprintf(
            'Restauration réussie en %d s — %d table(s) restaurée(s). Base jetable détruite.',
            $report['duration_seconds'],
            $report['tables'],
        ));

        return self::SUCCESS;
    }
}
