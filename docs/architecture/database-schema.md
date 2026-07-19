# Database Schema

> Source de vérité : `docs/architecture.md` § 6 et § 20 — shards **`6-modle-de-donnes.md`** et
> **`20-migrations-et-donnes-initiales.md`**. Voir aussi `data-models.md`.

## Ordre de dépendance des migrations

```
1. Platform        settings, audit_logs (+ déclencheurs), sessions, cache,
                   cache_locks, attachments, holidays
2. Identity        people → users (+ colonne générée) → rôles/permissions →
                   login_attempts
3. Finance         expense_categories → expenses → expense_approvals      [epic 4]
4. Work            objectives, projects, tasks, deliverables              [epic 5]
5. Accountability  daily_reports, blockers, weekly_reviews, internships   [epics 6-7]
6. Finance         accounts, clients, contracts, invoices, payments,
                   shares, reserve, reconciliations, closures             [epic 8]
```

`audit_logs` est créé **en premier**, avant toute table métier : le journal doit être opérationnel
*avant* la première écriture sensible.

`sessions`, `cache` et `cache_locks` sont des tables d'infrastructure framework, créées en fondation
(Sprint Change Proposal du 19/07/2026) : la matrice de privilèges de la story 1.5 et
`SESSION_DRIVER=database` les exigent dès le premier déploiement. `sessions.user_id` reste **sans
contrainte FK** — la table `users` appartient à l'epic 2.

## Règles non négociables

- **Une migration déployée n'est jamais modifiée.** Toute évolution est une nouvelle migration.
  C'est ce qui rend la restauration et la préproduction fiables.
- Déclencheurs, colonnes générées, contraintes `CHECK` et privilèges SQL sont créés **par migration**
  avec `DB::unprepared()` — jamais posés à la main sur le serveur, sinon préproduction et
  restauration divergent silencieusement.
- Nommage explicite : `2026_07_20_100000_add_payment_state_to_expenses_table.php`.
- Migrations exécutées sous l'utilisateur `ptrstaff_migrate` ; l'application tourne sous
  `ptrstaff_app`, **sans `DELETE`** sur les tables protégées.
- Seeders de référence **idempotents** (`updateOrCreate`), rejouables en production.
  `DemoSeeder` lève une exception hors développement.
