<?php

namespace App\Console\Commands;

use App\Models\Identity\User;
use App\Services\Identity\FirstAdminBootstrapService;
use App\Support\PhoneNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

#[Signature('ptr:create-first-admin')]
#[Description('Créer une seule fois le premier compte administrateur depuis une session SSH')]
class CreateFirstAdmin extends Command
{
    public function __construct(private readonly FirstAdminBootstrapService $bootstrapService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error("Amorçage refusé : un compte existe déjà dans l'installation.");

            return self::FAILURE;
        }

        $fullName = $this->ask('Nom complet');
        $phone = $this->ask('Téléphone');

        if (! is_string($fullName) || trim($fullName) === '') {
            $this->error('Le nom complet est obligatoire.');

            return self::FAILURE;
        }

        if (! is_string($phone)) {
            $this->error('Le téléphone est obligatoire.');

            return self::FAILURE;
        }

        try {
            $result = $this->bootstrapService->create(
                fullName: trim($fullName),
                normalizedPhone: PhoneNumber::normalize($phone),
            );
        } catch (InvalidArgumentException) {
            $this->error('Le téléphone saisi est invalide. Vérifiez-le puis recommencez.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->error("L'amorçage n'a pas abouti. Aucune donnée partielle n'a été conservée.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Mot de passe temporaire — affiché une seule fois : '.$result['temporary_password']);
        $this->warn('Conservez-le maintenant dans un emplacement sûr. Il ne pourra pas être récupéré.');
        $this->newLine();
        $this->info('Le compte super_admin a été créé.');
        $this->line('Ce rôle ne détient aucune permission métier.');
        $this->line('Première tâche : créer les deux comptes direction.');

        return self::SUCCESS;
    }
}
