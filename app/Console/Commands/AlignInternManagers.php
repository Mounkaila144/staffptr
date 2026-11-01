<?php

namespace App\Console\Commands;

use App\Enums\InternshipState;
use App\Models\Accountability\Internship;
use App\Services\Identity\HierarchyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Rattrapage de la décision du propriétaire du 2026-11-01 : le tuteur d'un stage actif est le
 * responsable direct du stagiaire.
 *
 * Idempotente : un stagiaire déjà rattaché à son tuteur n'est pas touché. Chaque alignement passe
 * par `HierarchyService::changeManager()` (verrou, contrôle de cycle, audit, historique).
 */
#[Signature('ptr:align-intern-managers {--dry-run : Affiche les alignements sans rien écrire}')]
#[Description('Aligne le responsable direct des stagiaires actifs sur leur tuteur')]
class AlignInternManagers extends Command
{
    public function __construct(private readonly HierarchyService $hierarchy)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $aligned = 0;
        $failed = 0;

        $internships = Internship::query()
            ->where('state', InternshipState::Actif)
            ->with(['intern.person', 'tutor.person'])
            ->orderBy('id')
            ->get();

        foreach ($internships as $internship) {
            $intern = $internship->intern;

            if ((int) $intern->manager_id === (int) $internship->tutor_id && $intern->manager_id !== null) {
                continue;
            }

            $line = sprintf(
                'Stage #%d — %s : responsable actuel %s, remplacé par le tuteur %s',
                $internship->getKey(),
                $intern->person->full_name,
                $intern->manager_id === null ? 'aucun' : "#{$intern->manager_id}",
                $internship->tutor->person->full_name,
            );

            if ($dryRun) {
                $this->line("[simulation] {$line}");
                $aligned++;

                continue;
            }

            try {
                $this->hierarchy->changeManager(
                    user: $intern,
                    managerId: (int) $internship->tutor_id,
                    actor: null,
                    reason: 'Rattrapage : le tuteur du stage devient le responsable direct du stagiaire (décision du 2026-11-01).',
                    actorLabel: 'Commande ptr:align-intern-managers',
                );
                $this->line($line);
                $aligned++;
            } catch (ValidationException $exception) {
                $this->components->error($line.' — refusé : '.implode(' ', $exception->validator->errors()->all()));
                $failed++;
            }
        }

        $this->components->info($dryRun
            ? "{$aligned} stagiaire(s) à aligner (simulation, rien n'a été écrit)."
            : "{$aligned} stagiaire(s) aligné(s) sur leur tuteur.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
