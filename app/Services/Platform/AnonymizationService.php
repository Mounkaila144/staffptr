<?php

namespace App\Services\Platform;

use App\Models\Finance\Client;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Anonymisation de la préproduction (story 11.1, AC 33 à 38).
 *
 * **Le refus en production est la première ligne du service, pas une option.** Une anonymisation
 * lancée par erreur sur la production détruirait irréversiblement l'identité de tout le personnel :
 * c'est la seule opération de ce dépôt dont l'erreur ne se répare pas par une contre-écriture.
 *
 * **Remplacements déterministes.** Un même identifiant produit toujours le même nom factice. Ce
 * n'est pas de l'élégance : une préproduction où les noms changent à chaque rafraîchissement rend
 * impossible de reproduire un bogue signalé par un testeur.
 *
 * **Ce que l'anonymisation n'est pas.** Elle n'autorise jamais un appel externe. La garde qui
 * empêche la préproduction de joindre WhatsApp repose sur la configuration d'environnement, pas
 * sur le fait que les numéros soient factices (AC 37) — sinon une anonymisation incomplète
 * enverrait des messages à de vraies personnes.
 */
final readonly class AnonymizationService
{
    /**
     * Numéro factice : `+227` suivi de 8 chiffres, comme l'exige {@see PhoneNumber}.
     *
     * La série commence par `00`, qui n'est attribuée à aucun opérateur nigérien — mobiles en `8`
     * et `9`, fixes en `20`. Un envoi accidentel n'atteint donc personne, ce qui est la seule
     * garantie utile : la garde d'environnement reste la protection principale (AC 37).
     */
    private const FAKE_PHONE_SERIES = '00';

    /**
     * @return array{people: int, users: int, clients: int, attachments: int}
     */
    public function run(): array
    {
        $this->assertNotProduction();

        return DB::transaction(function (): array {
            return [
                'people' => $this->anonymizePeople(),
                'users' => $this->anonymizeUsers(),
                'clients' => $this->anonymizeClients(),
                'attachments' => $this->anonymizeAttachments(),
            ];
        });
    }

    /**
     * AC 34 — refus dur en production.
     *
     * Le contrôle porte sur `APP_ENV` **et** sur l'URL : un `.env` mal copié peut laisser
     * `APP_ENV=staging` sur une machine qui sert le domaine de production. Deux signaux valent
     * mieux qu'un quand l'erreur est irréversible.
     */
    public function assertNotProduction(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                "L'anonymisation est refusée en production : elle détruirait des données réelles de façon irréversible.",
            );
        }

        $url = (string) config('app.url');

        if ($url !== '' && ! $this->looksLikeNonProductionHost($url)) {
            throw new RuntimeException(
                "L'anonymisation est refusée : APP_URL « {$url} » ressemble à un domaine de production.",
            );
        }
    }

    /**
     * Inventaire des champs personnels, exposé pour que le test de l'AC 36 les parcoure sans les
     * redéclarer — un test qui recopie la liste finirait par diverger d'elle.
     *
     * @return array<string, list<string>>
     */
    public static function sensitiveColumns(): array
    {
        return [
            'people' => ['full_name', 'photo_path'],
            'users' => ['phone'],
            'clients' => ['name', 'phone', 'contact'],
            'attachments' => ['original_name', 'path', 'thumbnail_path'],
        ];
    }

    private function anonymizePeople(): int
    {
        $count = 0;

        foreach (Person::query()->lazyById() as $person) {
            $person->forceFill([
                'full_name' => 'Personne '.$this->token((int) $person->getKey()),
                'photo_path' => null,
            ])->saveQuietly();
            $count++;
        }

        return $count;
    }

    private function anonymizeUsers(): int
    {
        $count = 0;

        foreach (User::query()->lazyById() as $user) {
            // Le numéro reste unique — la colonne générée l'exige — et reste hors des séries
            // réellement attribuées, pour qu'un envoi accidentel n'atteigne personne.
            $user->forceFill([
                'phone' => $this->fakePhone((int) $user->getKey()),
            ])->saveQuietly();
            $count++;
        }

        return $count;
    }

    private function anonymizeClients(): int
    {
        $count = 0;

        foreach (Client::query()->lazyById() as $client) {
            $client->forceFill([
                'name' => 'Client '.$this->token((int) $client->getKey()),
                // Décalage large : un client et un compte ne peuvent pas produire le même numéro.
                'phone' => $this->fakePhone(500_000 + (int) $client->getKey()),
                'contact' => null,
                'notes' => null,
            ])->saveQuietly();
            $count++;
        }

        return $count;
    }

    /**
     * Les pièces jointes ne sont pas seulement renommées : **le fichier lui-même est remplacé**.
     * Renommer une facture sans en effacer le contenu laisserait la donnée personnelle sur le
     * disque de préproduction (AC 33).
     */
    private function anonymizeAttachments(): int
    {
        $count = 0;

        foreach (Attachment::query()->lazyById() as $attachment) {
            $this->deleteFile((string) $attachment->disk, (string) $attachment->path);

            if ($attachment->thumbnail_path !== null) {
                $this->deleteFile((string) $attachment->disk, (string) $attachment->thumbnail_path);
            }

            $attachment->forceFill([
                'original_name' => 'piece-jointe-'.$this->token((int) $attachment->getKey()).'.'.$attachment->extension,
            ])->saveQuietly();
            $count++;
        }

        return $count;
    }

    private function deleteFile(string $disk, string $path): void
    {
        if ($path === '') {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable) {
            // Un fichier déjà absent n'est pas une erreur : l'objectif est qu'il ne reste pas.
        }
    }

    /**
     * Numéro factice unique et déterministe pour un identifiant donné. L'unicité importe : la
     * colonne générée `phone_unique_key` refuse un doublon sur les comptes non archivés.
     */
    private function fakePhone(int $id): string
    {
        return '+227'.self::FAKE_PHONE_SERIES.str_pad((string) ($id % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /** Jeton déterministe : le même identifiant produit toujours la même valeur factice. */
    private function token(int $id): string
    {
        return strtoupper(substr(hash('sha256', 'ptr-staff-anon:'.$id), 0, 6));
    }

    private function looksLikeNonProductionHost(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? $url));

        foreach (['localhost', '127.0.0.1', 'staging', 'preprod', 'recette', '.test', '.local'] as $marker) {
            if (str_contains($host, $marker)) {
                return true;
            }
        }

        return false;
    }
}
