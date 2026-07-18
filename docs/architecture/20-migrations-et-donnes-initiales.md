# 20. Migrations et données initiales

## 20.1 Migrations

- **Une migration n'est jamais modifiée après avoir été déployée.** Toute évolution est une nouvelle
  migration. C'est la règle qui rend la restauration et la préproduction fiables.
- Nommage explicite : `2026_07_20_100000_add_payment_state_to_expenses_table.php`.
- Les migrations tournent sous l'utilisateur MySQL privilégié (§ 14.1, § 25.4).
- Les déclencheurs, colonnes générées, contraintes `CHECK` et privilèges du § 14.1 sont créés **par
  migration** avec `DB::unprepared()` — jamais posés à la main sur le serveur, sinon la
  préproduction et la restauration divergent silencieusement.
- `down()` est écrit lorsqu'il a un sens ; sur les déclencheurs d'immuabilité, il est écrit mais
  **la restauration passe par la sauvegarde**, pas par un `migrate:rollback` en production.

## 20.2 Ordre de dépendance

```
1. Platform   settings, audit_logs (+ déclencheurs), attachments, holidays
2. Identity   people → users (+ colonne générée) → rôles/permissions → sessions, login_attempts
3. Finance    expense_categories → expenses → expense_approvals        [Étape 1]
4. Work       objectives, projects, tasks, deliverables                [Étape 2]
5. Accountability  daily_reports, blockers, weekly_reviews, internships [Étape 3]
6. Finance    accounts, clients, contracts, invoices, payments,
              shares, reserve, reconciliations, closures               [Étape 4]
```

`audit_logs` est créé **en premier**, avant toute table métier : le PRD exige un journal d'audit
opérationnel *avant* la première écriture sensible (Epic 1).

## 20.3 Données de référence — exécutées en production

Seeders **idempotents** (`updateOrCreate`), rejouables sans effet de bord :

| Seeder | Contenu | Source |
|---|---|---|
| `RolePermissionSeeder` | 6 rôles, jeu de permissions, matrice § 4.3 | FR11, PERM-* |
| `SettingSeeder` | Limite stagiaires **3** ; réserve **20 %** / **3 mois** ; rapport **17 h 45**, rappel **60 min** ; types et taille de pièces jointes | FR27, FR28, FR29, FR25 |
| `ExpenseCategorySeeder` | Catégories dont **« gratification de stagiaire »**, avec marqueur « essentielle » | FR126, FR127 |
| `FixedChargeSeeder` | Loyer, électricité, Internet, salaires — **paramétrables, non codés en dur** | FR138 |
| `HolidaySeeder` | Jours fériés nigériens de l'année en cours | FR25, D-02 |
| `CompanySeeder` | Fiche entreprise unique PTR Niger | FR13 |

> **DEC-09 — Q6 en attente.** Les comptes financiers réels (caisse, quelle banque, Airtel Money,
> Moov Money, autre) ne sont pas connus. Aucun seeder ne les invente : ils sont créés par écran à
> l'Étape 4. La liste reste requise avant de figer les écrans de rapprochement (Story 8.1 du plan d’exécution).

## 20.4 Données de démonstration

`DemoSeeder`, factories, **jamais exécuté hors développement**. Il porte une garde explicite qui
lève une exception si `app()->environment('production')`. Chaque modèle vient avec sa factory
(standards du dépôt).

---
