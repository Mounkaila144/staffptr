<?php

namespace App\Console\Commands;

use App\Services\Platform\AnonymizationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Anonymisation de la préproduction (story 11.1, AC 33, AC 34).
 *
 * La commande est destructrice et irréversible sur l'environnement où elle tourne. Deux gardes la
 * précèdent : le refus dur du service en production, et une confirmation interactive que seul
 * `--force` peut lever — pour les usages automatisés documentés dans
 * `docs/ops/staging-data.md`.
 */
#[Signature('ptr:anonymize {--force : Ne pas demander de confirmation (usage automatisé)}')]
#[Description('Remplace noms, téléphones et pièces jointes par des valeurs factices en préproduction')]
class Anonymize extends Command
{
    public function __construct(private readonly AnonymizationService $anonymization)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            // Le refus est vérifié **avant** toute question : on ne demande pas confirmation d'une
            // opération qui sera de toute façon refusée.
            $this->anonymization->assertNotProduction();
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'Cette opération remplace définitivement les données personnelles de cet environnement. Continuer ?',
            false,
        )) {
            $this->components->warn('Anonymisation annulée.');

            return self::SUCCESS;
        }

        $report = $this->anonymization->run();

        $this->components->info(sprintf(
            'Anonymisation terminée : %d personne(s), %d compte(s), %d client(s), %d pièce(s) jointe(s).',
            $report['people'],
            $report['users'],
            $report['clients'],
            $report['attachments'],
        ));

        return self::SUCCESS;
    }
}
