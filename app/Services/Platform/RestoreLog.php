<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Registre persistant des tests de restauration (story 11.1, AC 16, AC 55).
 *
 * **Pourquoi hors release.** Le déploiement est atomique : chaque version vit dans
 * `releases/<horodatage>/` et l'ancienne finit par être supprimée. Un registre écrit dans la
 * release disparaîtrait au troisième déploiement — exactement au moment où l'on voudrait prouver
 * qu'une restauration a été testée il y a six mois. Le fichier vit donc dans `shared/ops/`,
 * partagé entre les releases et hors de Git.
 *
 * La story signale un conflit documentaire : l'architecture § 21.3 indique encore
 * `docs/ops/restore-log.md`. Le PRD, plus récent, impose `shared/ops/` et en donne la raison.
 * Cette implémentation suit le PRD ; la procédure, elle, reste versionnée dans `docs/ops/`.
 */
final readonly class RestoreLog
{
    public function path(): string
    {
        $configured = config('ops.restore_log_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        // En local et en test, `shared/` n'existe pas : le registre reste dans le stockage de
        // l'application plutôt que d'échouer. La valeur de production est fournie par la
        // configuration d'environnement.
        return storage_path('app/ops/restore-log.md');
    }

    /**
     * Ajoute une entrée datée. Le fichier n'est jamais réécrit ni tronqué : un registre dont on
     * peut effacer une ligne ne prouve rien.
     *
     * @param  array<string, scalar|null>  $details
     */
    public function append(bool $succeeded, string $summary, array $details = []): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Le répertoire du registre de restauration est inaccessible : {$directory}.");
        }

        if (! file_exists($path)) {
            file_put_contents($path, $this->header());
        }

        $line = sprintf(
            "| %s | %s | %s | %s |\n",
            CarbonImmutable::now('Africa/Niamey')->format('Y-m-d H:i'),
            $succeeded ? 'RÉUSSI' : 'ÉCHEC',
            $this->singleLine($summary),
            $this->singleLine($this->formatDetails($details)),
        );

        if (file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException("Le registre de restauration n'a pas pu être écrit : {$path}.");
        }
    }

    /** @return list<string> */
    public function entries(): array
    {
        $path = $this->path();

        if (! file_exists($path)) {
            return [];
        }

        return array_values(array_filter(
            explode("\n", (string) file_get_contents($path)),
            static fn (string $line): bool => str_starts_with($line, '| 20'),
        ));
    }

    public function hasSuccessfulEntry(): bool
    {
        foreach ($this->entries() as $entry) {
            if (str_contains($entry, 'RÉUSSI')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Réduit une cellule à une seule ligne.
     *
     * Un message d'erreur de MariaDB contient des retours à la ligne et des barres verticales ; les
     * recopier tels quels **casserait le tableau Markdown** et rendrait le registre illisible
     * exactement le jour où on en a besoin. Le message est donc aplati et tronqué, avec l'essentiel
     * en tête — le détail complet reste dans le journal technique.
     */
    private function singleLine(string $value): string
    {
        $flat = trim((string) preg_replace('/\s+/u', ' ', str_replace('|', '/', $value)));

        return mb_strlen($flat) > 300 ? mb_substr($flat, 0, 297).'…' : $flat;
    }

    /** @param array<string, scalar|null> $details */
    private function formatDetails(array $details): string
    {
        $parts = [];

        foreach ($details as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = "{$key} : {$value}";
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    private function header(): string
    {
        return "# Registre des tests de restauration\n\n"
            ."> Écrit automatiquement par `php artisan ptr:test-restore`, et à la main après chaque\n"
            ."> restauration manuelle chronométrée. **Ne jamais réécrire une ligne existante.**\n"
            ."> Heures en `Africa/Niamey`.\n\n"
            ."| Date | Résultat | Résumé | Détails |\n"
            ."|---|---|---|---|\n";
    }
}
