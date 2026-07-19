# Tech Stack

> Source de vérité : `docs/architecture.md` § 4 et § 2. Ce fichier est chargé par l'agent `dev` à
> chaque story (`devLoadAlwaysFiles`). **Plus aucun choix n'est ouvert** — la section « À décider »
> de la version précédente est caduque depuis l'architecture v1.0 du 18/07/2026.

| Couche | Choix | Version | Réf. |
|---|---|---|---|
| Langage | PHP | 8.3 | Imposé — binaire local `/Applications/MAMP/bin/php/php8.3.30/bin/php` |
| Framework | Laravel | 13.x | Imposé, structure « slim » |
| Pont front | **Inertia.js** | 2.x | **A-01** — routage et autorisation restent serveur, pas de SPA séparée, **pas de SSR** |
| Front | Vue 3 (`<script setup>`) | 3.5+ | Imposé |
| Build | Vite | 8.x | Imposé |
| CSS | Tailwind CSS | 4.x | Config CSS-first via `@theme`, pas de `tailwind.config.js` |
| **BDD production** | **MySQL 8.0** / MariaDB 10.11+ | — | **A-02** |
| BDD développement | SQLite | 3.x | `database/database.sqlite` |
| **BDD des tests** | **MySQL** (service Docker en CI) | 8.0 | **DEC-02** — voir avertissement ci-dessous |
| Cache et files | Redis | 7.x | DEC-04 |
| **Sessions** | **MySQL** (driver `database`) | — | Nécessaire à FR8 / PERM-08 (invalidation immédiate) |
| Serveur web | Apache 2.4 + PHP-FPM | — | A-03, VPS partagé (DEC-05), **DEC-13** |
| Permissions | `spatie/laravel-permission` | 6.x | DEC-03 |
| Sauvegarde | `spatie/laravel-backup` | 9.x | NFR24, NFR25 |
| Images | `intervention/image` | 3.x | Vignettes serveur |
| Tests | PHPUnit (**pas Pest**) | 12.x | Imposé par `coding-standards.md` |
| Tests E2E | Playwright | 1.x | Parcours critiques uniquement |
| Formatage | Laravel Pint | 1.x | `vendor/bin/pint --dirty` |
| Analyse statique | Larastan (PHPStan) | 3.x | **Niveau 6** |
| CI/CD | GitHub Actions | — | Déploiement production sur **approbation manuelle** |

## Décisions tranchées — ne pas rouvrir

| Question | Réponse |
|---|---|
| Base de données de production ? | **MySQL 8** (A-02). SQLite est réservé au développement local. |
| Authentification ? | **Sessions Laravel stockées en base**, pas Sanctum, pas de jeton. Connexion par **téléphone `+227`** et mot de passe. Aucune inscription publique. |
| Multi-tenancy ? | **Non.** Aucune colonne de locataire dans le schéma (NFR28). |
| Découpage en modules ? | **Cinq modules** en sous-dossiers de namespace : `Platform`, `Identity`, `Work`, `Accountability`, `Finance`. Voir `source-tree.md`. |
| SPA séparée / API publique ? | **Non.** Inertia, pas d'API publique, pas de versioning de routes. |

## Avertissement — SQLite ne teste pas ce qui compte (DEC-02)

Les garanties les plus critiques du produit **n'existent pas ou se comportent différemment sous
SQLite** : déclencheurs d'immuabilité du journal d'audit, colonne générée d'unicité conditionnelle du
téléphone, contraintes `CHECK`, `lockForUpdate()`. Les tester sur un moteur qui ne les applique pas
reviendrait à ne pas les tester.

- **CI : MySQL obligatoire.**
- Local : SQLite acceptable pour les tests unitaires purs et rapides uniquement.

## Interdits

- **Aucune ressource tierce chargée à l'exécution** (NFR3) : ni CDN, ni police externe, ni script
  distant. Tout est servi par l'application. Cela interdit aussi toute bibliothèque de composants Vue.
- **Aucune intégration externe en MVP** : ni banque, ni Mobile Money, ni SMS, ni WhatsApp, ni courriel.
- Pas de conteneurisation en production (Docker sert uniquement à la CI).
- Pas de PWA, pas de service worker, pas de mode hors ligne (phase 2).

## Points d'attention Laravel 13

- Middleware, exceptions et routes se déclarent dans `bootstrap/app.php`.
- Les providers applicatifs sont listés dans `bootstrap/providers.php`.
- Les commandes de `app/Console/Commands/` sont auto-enregistrées.
- Les casts de modèle se déclarent dans une méthode `casts()`, pas la propriété `$casts`.
