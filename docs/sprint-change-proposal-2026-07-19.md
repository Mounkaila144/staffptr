# Sprint Change Proposal — Tables d'infrastructure manquantes (story 1.5)

Date : 2026-07-19 · Déclencheur : exécution opérateur de la story 1.5 sur le VPS · Tâche : `correct-course` (mode groupé)

## 1. Problème identifié

Le provisionnement du VPS est **suspendu** : la phase 2 du modèle SQL de `docs/ops/database-users.md`
accorde `UPDATE, DELETE` sur `sessions`, `cache` et `cache_locks`, mais **aucune migration ne crée ces
trois tables**. MariaDB renvoie `ERROR 1146 (42S02)` sur chacun des trois `GRANT`. Les grants sur
`jobs`, `job_batches` et `failed_jobs` ont réussi. Le déploiement de production a été volontairement
arrêté : publier l'application avec `SESSION_DRIVER=database` sans table `sessions` casserait au
premier visiteur.

**Chaîne causale :**

1. La story 1.1 a retiré les migrations Laravel par défaut `create_users_table` (qui contenait aussi
   `sessions` et `password_reset_tokens`) et `create_cache_table` (`cache`, `cache_locks`), décision
   consignée : « Les migrations Laravel de comptes et de cache ont été retirées ; seule
   l'infrastructure des jobs demeure. »
2. L'architecture (`database-schema.md` § ordre de dépendance) place `sessions` en **phase 2
   Identity** (epic 2) et ne mentionne `cache`/`cache_locks` **nulle part**.
3. La story 1.5 a contractualisé les trois tables **dès maintenant** : matrice de privilèges
   (`DELETE` obligatoire sur `sessions` — règle bloquante 10, § 9.3), phase 2 du modèle SQL, gabarits
   `.env` avec `SESSION_DRIVER=database`.
4. Personne n'a réconcilié 2 et 3. Les tests de la 1.5 sont des contrats sur le **texte** du modèle
   SQL, et la parité CI n'applique que `SELECT, INSERT` au schéma : aucun contrôle n'exécute la
   phase 2 contre une base migrée. Le trou n'était détectable qu'à l'exécution réelle — c'est le
   résidu OPS-001 du gate QA qui s'est matérialisé.

**Nature :** conflit entre artefacts validés (architecture vs story 1.5), révélé par une exigence
réelle nouvelle-née de l'exécution. Ni pivot, ni story ratée.

## 2. Impact sur les epics

- **Epic 1 :** la story 1.5 reste achevable ; elle a besoin d'un delta (migration d'infrastructure +
  preuve exécutable). Aucune autre story de l'epic n'est touchée. La 1.6 (HTTPS) reste bloquée tant
  que le provisionnement n'est pas terminé — dépendance déjà connue.
- **Epic 2 :** `users`, `password_reset_tokens` et `login_attempts` restent en epic 2, inchangés.
  Seule `sessions` est avancée en fondation ; la table Laravel standard ne porte **aucune contrainte
  FK** vers `users` (`user_id` nullable indexé), l'ordre de dépendance est donc respecté.
- **Epics 3+ :** aucun impact. Aucune création, suppression ni réordonnancement d'epic.

## 3. Conflits d'artefacts

| Artefact | Conflit | Action |
|---|---|---|
| `docs/architecture/database-schema.md` | `sessions` en phase Identity ; `cache`/`cache_locks` absents de l'ordre | Déplacer les trois en phase 1 Platform |
| `database/migrations/` | Aucune migration ne crée les trois tables | Nouvelle migration d'infrastructure |
| `docs/stories/1.5.story.md` | « Pas de migration nouvelle » contredit par la réalité d'exécution | Delta consigné (précédent : Task 2 bis) |
| `.github/workflows/pull-request-quality.yml` | La parité CI s'arrête aux grants de schéma ; la phase 2 n'est vérifiée nulle part | Appliquer les grants de phase 2 en CI après `migrate` |
| `docs/prd/ecarts-et-decisions.md` | Écart MariaDB 10.11 (VPS) vs MySQL 8 (stack/CI) tranché oralement le 19/07/2026, non consigné | Enregistrer **DEC-12** |
| `docs/ops/environments.md` | « SET PERSIST » (MySQL 8) inapplicable sur MariaDB | Note d'adaptation MariaDB dans l'acte d'exploitation |
| PRD, runbooks `database-users.md`/`secrets-rotation.md`, matrice de privilèges | Aucun conflit — la matrice devient vraie dès que les tables existent | Rien |

## 4. Chemins évalués et voie retenue

- **Option 1 — Ajustement direct (retenue) :** livrer un delta de la story 1.5 : migration créant
  `sessions`, `cache`, `cache_locks` + preuve exécutable en CI + alignement des documents. Effort
  faible, aucun travail jeté, débloque le VPS immédiatement.
- **Option 2 — Rollback :** rétablir les migrations supprimées en 1.1 — impossible sans recréer
  `users` prématurément (la migration d'origine les mêle) ; recréerait le problème inverse. Rejetée.
- **Option 3 — Contourner par la configuration (`SESSION_DRIVER=redis/file`) :** violerait
  l'architecture § 9.3 (révocation de session par `delete()` en base, règle bloquante 10, story 2.5)
  et rendrait fausse la matrice de privilèges livrée. Rejetée.
- **MVP :** inchangé.

## 5. Modifications proposées, artefact par artefact

### 5.1 Nouvelle migration `database/migrations/2026_07_19_120000_create_infrastructure_tables.php`

```php
Schema::create('sessions', function (Blueprint $table): void {
    $table->string('id')->primary();
    $table->foreignId('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->longText('payload');
    $table->integer('last_activity')->index();
});

Schema::create('cache', function (Blueprint $table): void {
    $table->string('key')->primary();
    $table->mediumText('value');
    $table->integer('expiration');
});

Schema::create('cache_locks', function (Blueprint $table): void {
    $table->string('key')->primary();
    $table->string('owner');
    $table->integer('expiration');
});
```

Schémas Laravel standard, sans contrainte FK (`user_id` nullable indexé — l'epic 2 n'est pas
anticipé). `down()` symétrique.

### 5.2 Test anti-régression (`tests/Feature/DatabasePrivilegeContractTest.php` ou dédié)

Nouveau test : **chaque table nommée par la phase 2 du modèle SQL existe après migration**
(`Schema::hasTable` sur les six tables d'infrastructure). Une table contractualisée par la matrice
mais créée par aucune migration fait échouer la suite — c'est exactement le défaut qui vient de se
produire.

### 5.3 CI — `.github/workflows/pull-request-quality.yml`

Après l'étape `php artisan migrate`, appliquer à `staffptr_app_ci` les grants de **phase 2**
(`UPDATE, DELETE` sur les six tables d'infrastructure de `staffptr_test`). Double effet : une table
manquante fait planter le `GRANT` (CI rouge — ce qui aurait détecté ce défaut), et la parité CI
couvre désormais la matrice complète, pas seulement le niveau schéma.

### 5.4 `docs/architecture/database-schema.md` — ordre de dépendance

```
1. Platform        settings, audit_logs (+ déclencheurs), sessions, cache,
                   cache_locks, attachments, holidays
2. Identity        people → users (+ colonne générée) → rôles/permissions →
                   login_attempts
```

Avec la note : « `sessions`, `cache` et `cache_locks` sont des tables d'infrastructure framework,
créées en fondation (correct-course du 19/07/2026) ; `sessions.user_id` reste sans contrainte FK. »

### 5.5 `docs/stories/1.5.story.md`

- Nouvelle tâche « **Task 10 — Delta correct-course : migration d'infrastructure** » (précédent :
  Task 2 bis) couvrant 5.1–5.3.
- Dev Notes § « Ce que cette story ne fait pas » : amender « Pas de migration nouvelle » en
  documentant l'exception et sa cause.
- Entrée au Change Log (version 1.7) référençant ce proposal.

### 5.6 `docs/prd/ecarts-et-decisions.md` — enregistrer DEC-12

| Réf. | Sujet | Échéance réelle | Qui décide |
|---|---|---|---|
| ~~DEC-12~~ | ✅ **Tranché 19/07/2026** — préprod et production sur **MariaDB 10.11** (instance existante du VPS partagé) au lieu de MySQL 8 ; divergence CI (MySQL 8) / production assumée ; adaptations d'exploitation : `SET GLOBAL` + fichier de conf au lieu de `SET PERSIST` | — | Direction |

Avec en « impact si renversé » : un passage à MySQL 8 dédié ne change que des valeurs de
provisionnement (propriété de réversibilité héritée de DEC-05).

### 5.7 `docs/ops/environments.md` — acte d'exploitation MySQL global

Ajouter la note : « Sur MariaDB (DEC-12), `SET PERSIST` n'existe pas : appliquer `SET GLOBAL` puis
persister le réglage dans un fichier de `/etc/mysql/mariadb.conf.d/`. »

## 6. Plan d'action

1. **Dev (`/dev`)** : implémenter 5.1–5.3 et 5.5, exécuter `php artisan test`, `vendor/bin/pint
   --dirty`, Larastan, `npm run build`, ouvrir la PR (six contrôles requis intacts).
2. **PO/pilote** : appliquer 5.4, 5.6, 5.7 (édition de documents) — peut être porté par la même PR.
3. **QA (`/qa`)** : revue du delta, mise à jour du gate 1.5.
4. **Exploitant (VPS)** : après merge — `git fetch && git pull` dans la release courante (ou nouvelle
   release), `php artisan migrate --force --database=mysql_migration` (staging puis production, avec
   les identifiants de migration injectés), rejouer les trois `GRANT` de phase 2 qui avaient échoué,
   puis reprendre les étapes 5 à 7 du prompt `docs/p.md`.
5. **Critère de succès** : les six `GRANT` de phase 2 passent sur les deux schémas, les quatre
   `SHOW GRANTS` sont consignés (lecture en négatif), `/up` répond 200 sur les deux hôtes — la story
   1.5 passe alors à Done.

## 7. Décisions d'approbation

- [ ] Proposal approuvé (sections 5.1 à 5.7)
- [ ] Implémentation confiée à l'agent dev
