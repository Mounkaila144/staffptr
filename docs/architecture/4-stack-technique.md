# 4. Stack technique

| Couche | Choix | Version | Justification |
|---|---|---|---|
| Langage | PHP | 8.3 | Imposé § 8.3 |
| Framework | Laravel | 13.x | Imposé § 8.3, structure « slim » |
| Pont front | Inertia.js | 2.x | A-01 — routage et autorisation restent serveur |
| Front | Vue 3 (`<script setup>`) | 3.5+ | Imposé § 8.3 |
| Build | Vite | 8.x | Imposé § 8.3 |
| CSS | Tailwind CSS | 4.x | Imposé § 8.3, configuration CSS-first via `@theme` |
| BDD production | MySQL / MariaDB | 8.0 / 10.11+ | A-02 |
| BDD développement | SQLite | 3.x | § 8.3 — voir réserve DEC-02 |
| Cache et files | Redis | 7.x | DEC-04 |
| Sessions | MySQL (`database`) | — | Nécessaire à FR8 / PERM-08, voir § 9.3 |
| Serveur web | Apache 2.4 + PHP-FPM | — | A-03, DEC-13 |
| Permissions | `spatie/laravel-permission` | 6.x | DEC-03 |
| Sauvegarde | `spatie/laravel-backup` | 9.x | NFR24, NFR25 |
| Images | `intervention/image` | 3.x | Vignettes serveur (UX § 11.2) |
| Tests | PHPUnit | 12.x | Imposé par `coding-standards.md` |
| Tests E2E | Playwright | 1.x | Parcours critiques § 23.4 |
| Formatage | Laravel Pint | 1.x | Imposé par `coding-standards.md` |
| Analyse statique | Larastan (PHPStan) | 3.x | Niveau 6, voir § 23.1 |
| CI/CD | GitHub Actions | — | § 25 |

**Poids du parc de dépendances front en production :** Vue 3 runtime (~34 Ko gzip) + Inertia
(~10 Ko gzip) + code applicatif découpé par page. Budget vérifié en CI (§ 19.4).

---
