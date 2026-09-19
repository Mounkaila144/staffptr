# Alimentation de la préproduction

> Story 11.1, Task 7 (AC 33 à 38).

## 1. Deux régimes, un basculement explicite (AC 35)

| Régime | Quand | Comment |
|---|---|---|
| **Factories** | Avant la première mise en production | `php artisan migrate:fresh --seed` |
| **Restauration + anonymisation** | Dès le Jalon 1 en service | Restaurer une sauvegarde de production, puis `ptr:anonymize` |

Le basculement de l'un à l'autre est **un acte daté**, consigné ci-dessous. Il ne se fait pas
« quand ça devient pratique » : le jour où la préproduction contient des données issues de la
production, elle change de nature et hérite de leurs obligations.

| Date du basculement | Décidé par | Jalon |
|---|---|---|
| _à renseigner_ | _à renseigner_ | _à renseigner_ |

## 2. Régime factories

```bash
cd /srv/staffptr/staging/current
php artisan migrate:fresh --seed
```

## 3. Régime restauration + anonymisation

```bash
cd /srv/staffptr/staging/current

# 1. Restaurer selon docs/ops/restore-procedure.md, dans le schéma de préproduction
# 2. Anonymiser IMMÉDIATEMENT — avant tout accès de testeur
php artisan ptr:anonymize --force

# 3. Vérifier
php artisan tinker --execute="dd(App\Models\Identity\Person::query()->pluck('full_name')->take(5));"
```

> **L'anonymisation suit la restauration sans délai.** Entre les deux, la préproduction contient
> des données réelles sous un contrôle d'accès plus faible que la production. Cette fenêtre doit se
> compter en minutes, pas en jours.

## 4. Ce que l'anonymisation remplace (AC 33)

| Table | Colonnes |
|---|---|
| `people` | `full_name`, `photo_path` |
| `users` | `phone` |
| `clients` | `name`, `phone`, `contact`, `notes` |
| `attachments` | `original_name` — **et le fichier lui-même est supprimé** |

Les pièces jointes ne sont pas seulement renommées : renommer une facture sans effacer son contenu
laisserait la donnée sur le disque de préproduction.

Les remplacements sont **déterministes** : un même identifiant produit toujours le même nom factice.
Une préproduction dont les noms changent à chaque rafraîchissement rend impossible de reproduire un
bogue signalé par un testeur.

## 5. Refus dur en production (AC 34)

`ptr:anonymize` refuse de s'exécuter si :

- `APP_ENV=production`, **ou**
- `APP_URL` ressemble à un domaine de production.

Deux signaux, parce que l'erreur est irréversible : un `.env` mal copié peut laisser
`APP_ENV=staging` sur une machine qui sert le domaine de production.

## 6. La préproduction n'émet jamais vers WhatsApp (AC 37)

La garde repose sur **la configuration d'environnement**, pas sur le fait que les numéros soient
factices :

```php
'allow_real_delivery' => env('EVOLUTION_ALLOW_REAL_DELIVERY', false) === true
    && env('APP_ENV') === 'production',
```

L'ordre des garanties compte. Si l'on comptait sur des numéros factices, une anonymisation
**incomplète** enverrait de vrais messages à de vraies personnes. La garde de configuration tient
même quand l'anonymisation a échoué.

## 7. La préproduction n'est pas sauvegardée (AC 37)

`BACKUP_LOCAL_ROOT` et les variables de stockage objet restent vides en préproduction. Sauvegarder
une préproduction reviendrait à créer une seconde copie, moins protégée, de données de production
anonymisées de façon peut-être imparfaite.
