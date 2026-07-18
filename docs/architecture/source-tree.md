# Source Tree

> Source de vérité : `docs/architecture.md` § 5. Chargé par l'agent `dev` à chaque story.
> Le découpage en modules se fait **par sous-dossiers de namespace dans l'arborescence Laravel
> standard** — pas en paquets Composer, pas en dossiers racine supplémentaires.

## Les cinq modules

| Module | Périmètre | Epic | Objets principaux |
|---|---|---|---|
| **Platform** | Paramètres, audit, notifications, pièces jointes, calendrier | 1, 3, 4 | `Setting`, `AuditLog`, `Attachment`, `Holiday` |
| **Identity** | Personnes, comptes, rôles, sessions, organisation | 2, 3 | `Person`, `User`, `Role`, `Absence`, `Department` |
| **Work** | Objectifs, projets, tâches, livrables | 5 | `Objective`, `Project`, `Task`, `Deliverable` |
| **Accountability** | Rapports, blocages, revues, stagiaires, documents | 6, 7 | `DailyReport`, `Blocker`, `WeeklyReview`, `Internship` |
| **Finance** | Dépenses et approbations (epic 4), puis comptes, contrats, encaissements, parts, réserve, clôture (epic 8) | 4 et 8 | `Account`, `Contract`, `Payment`, `Expense`, `Share`, `Reserve` |

## Arborescence

```
staffptr/
├── .bmad-core/            Framework BMAD (agents, tasks, templates, checklists)
├── .claude/commands/BMad/ Slash commands Claude Code
├── AGENTS.md              Définitions d'agents lues par Codex
├── app/
│   ├── Console/Commands/  ptr:create-first-admin, ptr:test-restore,
│   │                      ptr:check-invariants, ptr:anonymize
│   ├── Enums/             États, niveaux d'alerte, types de compte
│   ├── Http/
│   │   ├── Controllers/{Platform,Identity,Work,Accountability,Finance}/
│   │   ├── Requests/{…mêmes modules}/   Form Requests — validation exclusive
│   │   ├── Resources/                   API Resources
│   │   └── Middleware/                  EnsurePasswordChanged, EnsureAccountActive…
│   ├── Models/{Platform,Identity,Work,Accountability,Finance}/
│   ├── Observers/         Filet de sécurité d'audit
│   ├── Policies/{…mêmes modules}/       Une policy par modèle protégé
│   ├── Providers/
│   ├── Services/{…mêmes modules}/       Logique métier transactionnelle
│   └── Support/
│       ├── Money.php      Entiers XOF, formatage
│       ├── PhoneNumber.php  Normalisation +227
│       └── Auditing/      AuditLogger, trait Auditable
├── bootstrap/
│   ├── app.php            Middleware, exceptions, routing
│   └── providers.php
├── config/
│   ├── authorization-matrix.php   Rôle × route → statut attendu (NFR14)
│   └── …                  Seul endroit où `env()` est autorisé
├── database/{factories,migrations,seeders}/
├── docs/
│   ├── prd.md / prd/      PRD, epics générés depuis docs/epics-stories.md
│   ├── epics-stories.md   Plan d'exécution — SOURCE des fichiers epic
│   ├── architecture.md / architecture/
│   ├── front-end-spec.md
│   ├── ops/               Procédures d'exploitation, restore-log.md
│   ├── stories/           Stories générées par /sm
│   └── qa/                Gates et évaluations /qa
├── public/                Racine web — aucune pièce jointe ici
├── resources/
│   ├── css/
│   └── js/
│       ├── app.js         Amorçage Inertia
│       ├── Layouts/       AppLayout, AuthLayout
│       ├── Pages/{Platform,Identity,Work,Accountability,Finance}/
│       ├── Components/    Système de design propre — aucune bibliothèque externe
│       └── Composables/   useDraft, useMoney, usePermissions
├── routes/                web.php, console.php — pas d'api.php
├── storage/
│   └── app/private/       Pièces jointes — HORS racine web (NFR15)
├── tests/{Unit,Feature,Feature/Http,e2e}/
└── .ai/debug-log.md
```

## Règle de couplage — la seule imposée

Un service d'un module **peut lire** les modèles d'un autre module, mais **ne peut pas écrire**
dedans. Toute écriture inter-modules passe par le service propriétaire du modèle cible.

## Où placer quoi

- Nouvel écran → route dans `routes/web.php`, contrôleur dans `Http/Controllers/{Module}/`,
  Form Request dans `Http/Requests/{Module}/`, page Vue dans `resources/js/Pages/{Module}/`,
  **et déclaration dans `config/authorization-matrix.php`** si la route est protégée.
- Logique métier transactionnelle → `app/Services/{Module}/`.
- Règle d'accès → `app/Policies/{Module}/`, jamais dans le contrôleur.
- Calcul pur (parts, réserve, prorata, XOF) → `app/Support/` + test dans `tests/Unit/`.
- Test de bout en bout → `tests/Feature/` ; test HTTP et autorisation → `tests/Feature/Http/`.

**Ne créer aucun dossier racine nouveau.** Les fichiers se créent avec `php artisan make:*`, qui
accepte nativement le préfixe de module (`make:model Finance/Expense`).
