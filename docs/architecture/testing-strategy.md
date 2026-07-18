# Testing Strategy

> Source de vérité : `docs/architecture.md` § 23 (shard : `23-stratgie-de-tests.md`) et `docs/prd.md` § 8.5.
> Lu par `create-next-story` pour **toutes** les stories.

## Pyramide

| Niveau | Outil | Emplacement | Couvre |
|---|---|---|---|
| **Unitaire** | PHPUnit | `tests/Unit/` | Calculs purs : parts, réserve, alerte, prorata, normalisation de téléphone, formatage XOF |
| **Intégration** | PHPUnit + base | `tests/Feature/` | Services transactionnels, immuabilité, audit, verrous, clôture |
| **HTTP** | PHPUnit | `tests/Feature/Http/` | Contrôleurs, validation, codes de statut, **matrice d'autorisation** |
| **E2E** | Playwright | `tests/e2e/` | Parcours critiques réels, réseau bridé |

Larastan **niveau 6** dans la même chaîne CI. Tests PHPUnit, **pas Pest** :
`php artisan make:test --phpunit {Name}`.

## Base de test — MySQL, pas SQLite (DEC-02)

Voir `tech-stack.md`. Les déclencheurs d'immuabilité, la colonne générée d'unicité conditionnelle,
les contraintes `CHECK` et `lockForUpdate()` n'existent pas sous SQLite. **CI sur MySQL obligatoire.**

## Les 14 règles métier bloquantes — un test nommé chacune

**L'absence d'un seul de ces tests bloque la porte de qualité de l'epic.**

| # | Règle | Réf. | Epic |
|---|---|---|---|
| 1 | Maximum 3 objectifs majeurs validés par personne et par mois | RM-05, CA-05 | 5 |
| 2 | Maximum 5 priorités mensuelles d'entreprise | RM-04 | 5 |
| 3 | Maximum 3 stagiaires actifs par tuteur — bloquant | RM-06, CA-04 | 7 |
| 4 | Deux approbateurs **distincts**, sans seuil de montant | RM-09, CA-09 | 4 |
| 5 | Le demandeur n'est jamais approbateur, même `direction` | RM-10, CA-11 | 4 |
| 6 | Préparateur ≠ contrôleur sur rapprochement et rapport mensuel | RM-16, FR151 | 8 |
| 7 | Suppression financière impossible — modèle, route **et base** | RM-17, CA-12 | 8 |
| 8 | Aucune écriture imputable sur un mois clôturé | FR158, FR114 | 8 |
| 9 | `super_admin` n'a **aucune** permission métier | PERM-03 | 2 |
| 10 | La suspension invalide **toutes** les sessions immédiatement | FR8, PERM-08 | 2 |
| 11 | L'échec d'écriture d'audit annule l'opération métier | NFR21 | 1 |
| 12 | Unicité du téléphone sur comptes non archivés uniquement | FR3 | 2 |
| 13 | Les parts 10 % / 30 % restent payables en alerte rouge | RM-14, FR165 | 9 |
| 14 | La somme des parts est exactement égale à la base (reste entier) | FR130, NFR22 | 8 |

## Campagne d'autorisation — NFR14 / CA-02

`tests/Feature/Http/AuthorizationMatrixTest.php`, alimenté par `config/authorization-matrix.php`
(transcription directe de la matrice PRD § 4.3).

Deux propriétés en font un dispositif utile plutôt qu'un test de plus :

1. **Un test complémentaire échoue si une route protégée déclarée n'apparaît pas dans la matrice.**
   Ajouter une route sans déclarer sa politique d'accès casse la chaîne.
2. `403` et `404` sont **distingués de toute redirection** — PERM-02 interdit la redirection
   silencieuse, et un test qui accepterait une `302` validerait le défaut qu'on cherche à empêcher.

Exécutée à chaque pull request **et** rejouée en porte de qualité de chaque jalon.

## E2E — Playwright

Limité aux parcours où le chronomètre et l'ergonomie font partie de l'exigence :

| Parcours | Vérifie |
|---|---|
| Rapport quotidien de bout en bout, réseau bridé | NFR4 (< 3 min), NFR5 (brouillon) |
| Approbation de dépense depuis la notification | FR121, **3 interactions maximum** |
| Connexion → changement de mot de passe imposé | FR5 |
| Encaissement → calcul des parts → réseau | FR113, FR131, FR143 |

Playwright bride le réseau à **400 kbit/s / 400 ms**. **Cela ne remplace pas la recette sur téléphone
réel**, qui reste obligatoire et opposable avant chaque mise en service.

## Règles générales

- Un test par changement, exécuté (`coding-standards.md`).
- Couvrir chemin nominal, chemins d'erreur et cas limites.
- Factories et states plutôt qu'un setup manuel ; chaque modèle vient avec sa factory.
- Toute story porte en plus le socle transverse `docs/prd/socle-transverse.md` (SOC-01 à SOC-11).
