<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * État agrégé de la sauvegarde (story 11.1, AC 6, AC 10, AC 11).
 *
 * Un seul point de vérité, consommé par trois appelants qui doivent dire la même chose :
 * `/up`, `ptr:check-invariants` et les runbooks d'exploitation.
 *
 * **Ce que cette classe ne dit jamais.** Ni le nom du bucket, ni l'endpoint, ni le chemin des
 * archives, ni la phrase secrète. `/up` est une route **publique** (AC 10) : elle expose un âge et
 * un état, rien qui aide à trouver ou à ouvrir une sauvegarde. C'est la raison pour laquelle la
 * méthode publique retourne un tableau volontairement pauvre.
 */
final readonly class BackupStatus
{
    /** Seuil de l'invariant (AC 11) : au-delà, une exécution quotidienne a été manquée. */
    public const MAX_AGE_HOURS = 26;

    private const LOCAL_DISK = 'backups';

    private const OFFSITE_DISK = 'backups-offsite';

    /**
     * Âge de la sauvegarde la plus récente, en heures, ou `null` s'il n'en existe aucune.
     *
     * `null` n'est pas « zéro » : ne pas savoir n'est pas être à jour. Les appelants doivent
     * traiter les deux cas séparément.
     */
    public function ageInHours(): ?int
    {
        $latest = $this->latestTimestamp();

        if ($latest === null) {
            return null;
        }

        return (int) CarbonImmutable::now('UTC')->diffInHours(
            CarbonImmutable::createFromTimestamp($latest, 'UTC'),
            absolute: true,
        );
    }

    public function isFresh(): bool
    {
        $age = $this->ageInHours();

        return $age !== null && $age <= self::MAX_AGE_HOURS;
    }

    /**
     * La destination hors site est-elle configurée ? Sans DEC-06, elle ne l'est pas.
     *
     * Une variable **déclarée vide** dans `.env` vaut chaîne vide, pas `null` : ne tester que
     * `null` ferait passer `BACKUP_OBJECT_BUCKET=` pour une configuration valide et provoquerait
     * une tentative d'écriture S3 sans bucket.
     */
    public function offsiteConfigured(): bool
    {
        return ! in_array(config('filesystems.disks.'.self::OFFSITE_DISK.'.bucket'), [null, ''], true);
    }

    /**
     * Forme exposée par `/up` (AC 10). Aucun chemin, aucun fournisseur, aucun secret : seulement
     * ce qu'une surveillance externe a besoin de savoir pour alerter.
     *
     * @return array{state: string, age_hours: int|null, max_age_hours: int, offsite_configured: bool}
     */
    public function toHealthPayload(): array
    {
        $age = $this->ageInHours();

        return [
            'state' => match (true) {
                $age === null => 'absente',
                $age <= self::MAX_AGE_HOURS => 'fraiche',
                default => 'perimee',
            },
            'age_hours' => $age,
            'max_age_hours' => self::MAX_AGE_HOURS,
            'offsite_configured' => $this->offsiteConfigured(),
        ];
    }

    /**
     * Horodatage UNIX de l'archive la plus récente, tous disques confondus.
     *
     * Un disque injoignable est ignoré plutôt que fatal : si le stockage hors site est en panne
     * mais que la copie locale du jour existe, la sauvegarde **est** fraîche. C'est l'absence des
     * deux qui doit alerter.
     */
    private function latestTimestamp(): ?int
    {
        $latest = null;

        foreach ([self::LOCAL_DISK, self::OFFSITE_DISK] as $disk) {
            if (! is_array(config('filesystems.disks.'.$disk))) {
                continue;
            }

            if ($disk === self::OFFSITE_DISK && ! $this->offsiteConfigured()) {
                continue;
            }

            try {
                foreach (Storage::disk($disk)->allFiles() as $file) {
                    if (! str_ends_with($file, '.zip')) {
                        continue;
                    }

                    $latest = max($latest ?? 0, Storage::disk($disk)->lastModified($file));
                }
            } catch (Throwable) {
                // Disque injoignable : il ne prouve ni la présence ni l'absence d'une sauvegarde.
                continue;
            }
        }

        return $latest;
    }
}
