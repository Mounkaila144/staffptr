# Comptes MySQL et matrice de privilèges

> ⛔ **Consigne permanente — toute migration créant une table métier à partir de la story 2.1
> s'accompagne d'une ligne `GRANT UPDATE` dans ce document.**
>
> `SELECT` et `INSERT` sont hérités du schéma ; `UPDATE` ne l'est pas. Sans cette ligne,
> l'application lira et insérera dans la nouvelle table mais **ne pourra pas modifier une ligne
> existante**, et la panne se manifestera à la première mise à jour, pas à la migration. C'est le
> prix assumé du « refusé par défaut ». La CI applique la même matrice : une table métier livrée
> sans sa ligne produit une **chaîne rouge**, pas un incident de préproduction.

Ce document est l'artefact opposable des droits PTR Staff. Les comptes MySQL écoutent sur
`localhost` uniquement. Le caractère générique d'hôte est réservé à la CI en conteneur.

## Comptes et frontières

| Environnement | Schéma autorisé | Compte applicatif | Compte de migration |
|---|---|---|---|
| Préproduction | `ptrstaff_staging` | `ptrstaff_staging_app@localhost` | `ptrstaff_staging_migrate@localhost` |
| Production | `ptrstaff_prod` | `ptrstaff_prod_app@localhost` | `ptrstaff_prod_migrate@localhost` |

Chaque compte n'accède qu'au schéma de sa ligne. Les comptes de migration reçoivent `ALL
PRIVILEGES` et `GRANT OPTION` sur leur schéma uniquement, sans privilège global, `SUPER` ou accès à
un schéma voisin.

## Portée des privilèges

| Privilège | Portée | Tables futures |
|---|---|---|
| `SELECT` | **schéma** | héritées d'office |
| `INSERT` | **schéma** | héritées d'office |
| `UPDATE` | **par table** | à accorder explicitement |
| `DELETE` | **par table**, infrastructure uniquement | **refusé par défaut** |

Cette asymétrie suit une propriété de MySQL, elle n'est pas un compromis de confort : **les
privilèges sont cumulatifs entre niveaux et ne se soustraient pas**. Aucun `REVOKE` au niveau table
ne reprend ce qui a été accordé au niveau schéma. `SELECT` et `INSERT` ne peuvent donc être portés au
schéma que parce qu'ils sont voulus universellement ; `UPDATE` et `DELETE` restent par table
précisément pour rester refusables. Un `GRANT UPDATE ON schéma.*` accordé « pour simplifier » ne se
reprendrait plus jamais table par table — il faudrait recréer le compte.

Règle de lecture du modèle SQL : **si vous voyez `UPDATE` ou `DELETE`, c'est toujours sur une table
nommée.** Le niveau schéma ne porte que `SELECT, INSERT`.

## Matrice applicative

| Catégorie ou table | `SELECT` | `INSERT` | `UPDATE` | `DELETE` |
|---|---:|---:|---:|---:|
| tables métier et financières des epics 2 à 8 | hérité du schéma | hérité du schéma | **explicite, par table** | **refusé** |
| `audit_logs` | hérité du schéma | hérité du schéma | **refusé** | **refusé** |
| `user_history` | hérité du schéma | hérité du schéma | **refusé** | **refusé** |
| `settings` | hérité du schéma | hérité du schéma | **explicite, par table** | **refusé** |
| `attachments` | hérité du schéma | hérité du schéma | **explicite, par table** | **refusé** |
| `person_documents` | hérité du schéma | hérité du schéma | **explicite, par table** | **refusé** |
| `notifications` | hérité du schéma | hérité du schéma | **explicite, par table** | **refusé** |
| `internal_documents` | hérité du schéma | hérité du schéma | **explicite, par table** | **refusé** |
| `internal_document_versions` | explicite | explicite | **refusé** | **refusé** |
| `internal_document_acknowledgements` | explicite | explicite | **refusé** | **refusé** |
| `holidays` | hérité du schéma | hérité du schéma | **explicite, par table** | **refusé** |
| `absences` | hérité du schéma | hérité du schéma | **explicite, par table** | **refusé** |
| `expense_categories` | hérité du schéma | hérité du schéma | **explicite, par table** | **refusé** |
| `sessions` | hérité du schéma | hérité du schéma | oui | **accordé** |
| `jobs` | hérité du schéma | hérité du schéma | oui | **accordé** |
| `job_batches` | hérité du schéma | hérité du schéma | oui | **accordé** |
| `failed_jobs` | hérité du schéma | hérité du schéma | oui | **accordé** |
| `cache` | hérité du schéma | hérité du schéma | oui | **accordé** |
| `cache_locks` | hérité du schéma | hérité du schéma | oui | **accordé** |
| `roles`, `permissions`, `role_has_permissions` | hérité du schéma | hérité du schéma | **explicite** | **refusé** |
| `model_has_roles`, `model_has_permissions` | hérité du schéma | hérité du schéma | **explicite** | **accordé par exception RBAC** |
| `login_attempts` | hérité du schéma | hérité du schéma | **explicite** | **refusé** |
| toute table créée ultérieurement | **hérité d'office** | **hérité d'office** | **absent tant qu'il n'est pas accordé** | **refusé par défaut** |

Une migration qui crée une table métier n'a donc rien à faire pour la lecture et l'insertion, mais
doit accorder explicitement `UPDATE` au compte applicatif lu depuis la configuration. Elle n'accorde
jamais `DELETE` sans exception motivée, revue et tracée dans ce document. `DELETE` sur `sessions` est
obligatoire pour la révocation immédiate des sessions suspendues ; Laravel supprime également les
files, verrous et caches expirés.

L'exception RBAC a été revue et accordée le 20/07/2026 pour `model_has_roles` et
`model_has_permissions` uniquement. `spatie/laravel-permission` ajoute une affectation par
`INSERT`, mais ses opérations `removeRole()`, `revokePermissionTo()`, `syncRoles()` et
`syncPermissions()` retirent les lignes courantes par `DELETE`. Ces pivots décrivent l'état courant
d'une affectation, pas son historique : `RoleAssignmentService` conserve obligatoirement chaque
attribution, modification et retrait avec ses anciennes et nouvelles valeurs dans `audit_logs`, au
sein de la même transaction. `DELETE` reste interdit sur `roles`, `permissions` et
`role_has_permissions`, qui forment le catalogue de référence.

La migration d'audit 1.4 pose un `GRANT SELECT, INSERT` sur `audit_logs`. Elle lit
`AUDIT_DB_APP_USERNAME` et `AUDIT_DB_APP_HOST` depuis la configuration et ne code aucun compte en
dur. Le `GRANT OPTION` du compte de migration lui permet cette délégation sans sortir du schéma.

Ce grant est **redondant** avec le grant de schéma depuis l'introduction des portées ci-dessus, mais
il n'est ni faux ni superflu et **doit être conservé** : les privilèges MySQL se cumulent, il rend la
migration portable entre CI, préproduction et production sans configuration supplémentaire, et il
documente l'intention sur la table la plus sensible du schéma. `UPDATE` et `DELETE` sur `audit_logs`
restent refusés parce qu'ils ne sont accordés à **aucun** niveau — ni schéma, ni table.

La migration de `user_history` reprend volontairement le même `GRANT SELECT, INSERT` explicite et
les mêmes limites. Ce registre permanent est alimenté uniquement par insertion : aucun `GRANT
UPDATE` ni `DELETE` ne doit lui être ajouté, même lors d'une intervention corrective.

## Modèle SQL idempotent

Les marqueurs `{{...}}` sont volontairement inutilisables et doivent être substitués en mémoire à
partir du magasin de secrets. L'amorce est exécutée en deux phases : la première avant les
migrations, la seconde après création des tables d'infrastructure. Elle peut être rejouée.

```sql
-- Phase 1 : schéma et comptes de production
CREATE DATABASE IF NOT EXISTS `ptrstaff_prod`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ptrstaff_prod_app'@'localhost'
  IDENTIFIED BY '{{PTRSTAFF_PROD_APP_PASSWORD}}';
ALTER USER 'ptrstaff_prod_app'@'localhost'
  IDENTIFIED BY '{{PTRSTAFF_PROD_APP_PASSWORD}}';
CREATE USER IF NOT EXISTS 'ptrstaff_prod_migrate'@'localhost'
  IDENTIFIED BY '{{PTRSTAFF_PROD_MIGRATE_PASSWORD}}';
ALTER USER 'ptrstaff_prod_migrate'@'localhost'
  IDENTIFIED BY '{{PTRSTAFF_PROD_MIGRATE_PASSWORD}}';
GRANT ALL PRIVILEGES ON `ptrstaff_prod`.*
  TO 'ptrstaff_prod_migrate'@'localhost' WITH GRANT OPTION;
-- Lecture et insertion au niveau du schéma : héritées par toute table future.
-- Ni UPDATE ni DELETE ici — ils ne se reprendraient plus table par table.
GRANT SELECT, INSERT ON `ptrstaff_prod`.* TO 'ptrstaff_prod_app'@'localhost';

-- Phase 1 : schéma et comptes de préproduction
CREATE DATABASE IF NOT EXISTS `ptrstaff_staging`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ptrstaff_staging_app'@'localhost'
  IDENTIFIED BY '{{PTRSTAFF_STAGING_APP_PASSWORD}}';
ALTER USER 'ptrstaff_staging_app'@'localhost'
  IDENTIFIED BY '{{PTRSTAFF_STAGING_APP_PASSWORD}}';
CREATE USER IF NOT EXISTS 'ptrstaff_staging_migrate'@'localhost'
  IDENTIFIED BY '{{PTRSTAFF_STAGING_MIGRATE_PASSWORD}}';
ALTER USER 'ptrstaff_staging_migrate'@'localhost'
  IDENTIFIED BY '{{PTRSTAFF_STAGING_MIGRATE_PASSWORD}}';
GRANT ALL PRIVILEGES ON `ptrstaff_staging`.*
  TO 'ptrstaff_staging_migrate'@'localhost' WITH GRANT OPTION;
-- Lecture et insertion au niveau du schéma : héritées par toute table future.
-- Ni UPDATE ni DELETE ici — ils ne se reprendraient plus table par table.
GRANT SELECT, INSERT ON `ptrstaff_staging`.* TO 'ptrstaff_staging_app'@'localhost';

-- Phase 2 : modification et suppression, toujours table par table.
-- Infrastructure de production — seules tables autorisées à DELETE.
GRANT UPDATE, DELETE ON `ptrstaff_prod`.`sessions` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE, DELETE ON `ptrstaff_prod`.`jobs` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE, DELETE ON `ptrstaff_prod`.`job_batches` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE, DELETE ON `ptrstaff_prod`.`failed_jobs` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE, DELETE ON `ptrstaff_prod`.`cache` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE, DELETE ON `ptrstaff_prod`.`cache_locks` TO 'ptrstaff_prod_app'@'localhost';

-- Infrastructure de préproduction.
GRANT UPDATE, DELETE ON `ptrstaff_staging`.`sessions` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE, DELETE ON `ptrstaff_staging`.`jobs` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE, DELETE ON `ptrstaff_staging`.`job_batches` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE, DELETE ON `ptrstaff_staging`.`failed_jobs` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE, DELETE ON `ptrstaff_staging`.`cache` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE, DELETE ON `ptrstaff_staging`.`cache_locks` TO 'ptrstaff_staging_app'@'localhost';

-- Phase 3 : tables métier, à compléter story par story à partir de 2.1.
-- Une ligne GRANT UPDATE par table métier créée. Jamais de DELETE.
GRANT UPDATE ON `ptrstaff_prod`.`people` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`users` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`roles` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`permissions` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`model_has_roles` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`model_has_permissions` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`role_has_permissions` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`login_attempts` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`companies` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`departments` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`job_functions` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`settings` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`attachments` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`person_documents` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`notifications` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`internal_documents` TO 'ptrstaff_prod_app'@'localhost';
GRANT SELECT, INSERT ON `ptrstaff_prod`.`internal_document_versions` TO 'ptrstaff_prod_app'@'localhost';
GRANT SELECT, INSERT ON `ptrstaff_prod`.`internal_document_acknowledgements` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`holidays` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`absences` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`expense_categories` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`expenses` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`expense_approvals` TO 'ptrstaff_prod_app'@'localhost';
-- Epics 4 à 11 : travail, redevabilité, stages et finance. Rattrapées par la migration
-- 2026_09_19_201146 : leurs migrations d'origine avaient omis ce GRANT, si bien qu'aucune
-- écriture — ni même un SELECT ... FOR UPDATE — n'aboutissait sous le compte applicatif.
-- `account_movements`, les tables de versions et d'historiques restent volontairement
-- absentes : elles sont immuables. `reserve_movements` y figure car le verrou de lecture
-- exige UPDATE, son trigger demeurant la garde réelle.
GRANT UPDATE ON `ptrstaff_prod`.`accounts` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`alert_level_states` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`blockers` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`clients` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`company_priorities` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`contract_executors` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`contracts` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`correction_plans` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`daily_report_comments` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`daily_report_decisions` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`daily_reports` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`deliverables` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`fixed_charges` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`improvement_plan_actions` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`improvement_plans` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`internship_checklist_items` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`internship_evaluations` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`internship_intake_forms` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`internship_intake_outcomes` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`internship_plans` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`internships` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`invoices` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`month_closures` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`monthly_budgets` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`monthly_reports` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`objectives` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`payments` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`project_members` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`projects` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`receipt_sequences` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`reconciliations` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`reserve_movements` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`saved_filters` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`share_entitlements` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`support_request_batch_items` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`support_request_batches` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`task_requests` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`tutor_support_slots` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`weekly_review_objectives` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`weekly_reviews` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`work_comments` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`work_links` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_prod`.`work_tasks` TO 'ptrstaff_prod_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`people` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`users` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`roles` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`permissions` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`model_has_roles` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`model_has_permissions` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`role_has_permissions` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`login_attempts` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`companies` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`departments` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`job_functions` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`settings` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`attachments` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`person_documents` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`notifications` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`internal_documents` TO 'ptrstaff_staging_app'@'localhost';
GRANT SELECT, INSERT ON `ptrstaff_staging`.`internal_document_versions` TO 'ptrstaff_staging_app'@'localhost';
GRANT SELECT, INSERT ON `ptrstaff_staging`.`internal_document_acknowledgements` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`holidays` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`absences` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`expense_categories` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`expenses` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`expense_approvals` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`accounts` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`alert_level_states` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`blockers` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`clients` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`company_priorities` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`contract_executors` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`contracts` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`correction_plans` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`daily_report_comments` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`daily_report_decisions` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`daily_reports` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`deliverables` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`fixed_charges` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`improvement_plan_actions` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`improvement_plans` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`internship_checklist_items` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`internship_evaluations` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`internship_intake_forms` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`internship_intake_outcomes` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`internship_plans` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`internships` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`invoices` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`month_closures` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`monthly_budgets` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`monthly_reports` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`objectives` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`payments` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`project_members` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`projects` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`receipt_sequences` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`reconciliations` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`reserve_movements` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`saved_filters` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`share_entitlements` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`support_request_batch_items` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`support_request_batches` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`task_requests` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`tutor_support_slots` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`weekly_review_objectives` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`weekly_reviews` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`work_comments` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`work_links` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`work_tasks` TO 'ptrstaff_staging_app'@'localhost';

-- Phase 4 : exception RBAC revue le 20/07/2026 — état courant audité, pivots uniquement.
GRANT DELETE ON `ptrstaff_prod`.`model_has_roles` TO 'ptrstaff_prod_app'@'localhost';
GRANT DELETE ON `ptrstaff_prod`.`model_has_permissions` TO 'ptrstaff_prod_app'@'localhost';
GRANT DELETE ON `ptrstaff_staging`.`model_has_roles` TO 'ptrstaff_staging_app'@'localhost';
GRANT DELETE ON `ptrstaff_staging`.`model_has_permissions` TO 'ptrstaff_staging_app'@'localhost';
```

Le réglage global `log_bin_trust_function_creators` ne figure volontairement pas dans ce modèle :
il suit l'acte d'exploitation séparé décrit dans `docs/ops/environments.md`.

## Vérification et preuve d'exécution

Après les migrations et la phase 2, exécuter :

```sql
SHOW GRANTS FOR 'ptrstaff_prod_app'@'localhost';
SHOW GRANTS FOR 'ptrstaff_prod_migrate'@'localhost';
SHOW GRANTS FOR 'ptrstaff_staging_app'@'localhost';
SHOW GRANTS FOR 'ptrstaff_staging_migrate'@'localhost';
```

Joindre la sortie horodatée au journal d'exploitation. La revue est négative autant que positive :

1. chaque compte ne cite que son propre schéma ;
2. aucun droit global ni `SUPER` n'apparaît ;
3. **la ligne de schéma d'un compte applicatif ne porte que `SELECT, INSERT`** — y voir `UPDATE` ou
   `DELETE` signifie que la matrice est perdue et que le compte doit être recréé, la reprise par
   table étant impossible ;
4. les comptes applicatifs n'ont aucun `DELETE` sur une table métier, hormis l'exception motivée
   limitée aux pivots `model_has_roles` et `model_has_permissions` ;
5. `audit_logs` ne reçoit ni `UPDATE` ni `DELETE`, à aucun niveau ;
6. `user_history` ne reçoit ni `UPDATE` ni `DELETE`, à aucun niveau ;
7. les six tables d'infrastructure accordent bien `UPDATE, DELETE` ;
8. `people`, `users`, `settings`, puis chaque future table métier, portent leur ligne `GRANT UPDATE`, conformément à la consigne
   permanente en tête de ce document ;
9. seuls les comptes de migration disposent de `GRANT OPTION`.

## Actions dues à l'exploitant pour les epics 4 à 11

Les quatorze migrations d'août 2026 ont créé leurs tables sans accorder `UPDATE` au compte
applicatif, contrairement à la règle posée en tête de ce document. La migration
`2026_09_19_201146_grant_application_update_on_work_accountability_and_finance_tables` rattrape les
quarante-trois lignes manquantes ; `GRANT` étant idempotent, elle peut être rejouée sans risque.

Après déploiement, joindre la sortie `SHOW GRANTS` horodatée au journal d'exploitation et la lire
**en négatif** : aucun `DELETE` ne doit apparaître en dehors de l'infrastructure (`sessions`,
`jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`) et des deux pivots RBAC ; `audit_logs`
et `user_history` doivent rester strictement en `SELECT, INSERT`.

## Actions dues à l'exploitant pour la story 2.6

Après la migration de `login_attempts`, exécuter les deux lignes `GRANT UPDATE` de phase 3 sur la
préproduction puis sur la production avec le compte de migration de chaque environnement. Joindre
les sorties `SHOW GRANTS` horodatées au journal d'exploitation et vérifier explicitement que
`DELETE` reste absent sur `login_attempts`.

## Actions dues à l'exploitant pour la story 2.10

Aucun nouveau privilège n'est requis. La migration ajoute uniquement des index à `audit_logs` ;
le compte applicatif conserve strictement `SELECT, INSERT` sur cette table, sans `UPDATE` ni
`DELETE`. Après déploiement, joindre la sortie `SHOW GRANTS` horodatée au journal d'exploitation
et vérifier que cette interdiction reste inchangée.

## Actions dues à l'exploitant pour la story 3.1

Après les migrations de `companies`, `departments` et `job_functions`, exécuter les six lignes
`GRANT UPDATE` correspondantes de phase 3 sur la préproduction puis sur la production avec le
compte de migration de chaque environnement. Joindre les sorties `SHOW GRANTS` horodatées au
journal d'exploitation et vérifier que `DELETE` reste absent sur ces trois tables.

## Actions dues à l'exploitant pour la story 3.3

Aucun privilège de modification n'est requis. Après la migration, vérifier la présence des
déclencheurs MySQL `user_history_prevent_update` et `user_history_prevent_delete`, puis joindre la
sortie `SHOW GRANTS` horodatée au journal d'exploitation. Le compte applicatif doit conserver
uniquement `SELECT, INSERT` sur `user_history` ; `UPDATE` et `DELETE` doivent rester absents.

## Actions dues à l'exploitant pour la story 3.4

La migration de `settings` accorde elle-même `UPDATE` au compte applicatif configuré. Après
déploiement, joindre la sortie `SHOW GRANTS` horodatée au journal d'exploitation et vérifier que
`settings` porte `SELECT`, `INSERT` et `UPDATE`, sans aucun `DELETE`.

## Actions dues à l'exploitant pour la story 3.5

La migration de `attachments` accorde elle-même `UPDATE` au compte applicatif configuré afin que
le worker puisse renseigner `thumbnail_path`. Après déploiement, joindre la sortie `SHOW GRANTS`
horodatée et vérifier que `attachments` porte `SELECT`, `INSERT` et `UPDATE`, sans aucun `DELETE`.

## Actions dues à l'exploitant pour la story 3.6

La migration de `person_documents` accorde elle-même `UPDATE` au compte applicatif configuré pour
l'archivage motivé. Après déploiement, joindre la sortie `SHOW GRANTS` horodatée et vérifier que
`person_documents` porte `SELECT`, `INSERT` et `UPDATE`, sans aucun `DELETE`.

## Actions dues à l'exploitant pour la story 3.7

La migration de `notifications` accorde elle-même `UPDATE` au compte applicatif afin de renseigner
`read_at`. Après déploiement, joindre la sortie `SHOW GRANTS` horodatée et vérifier que
`notifications` porte `SELECT`, `INSERT` et `UPDATE`, sans aucun `DELETE`.

## Actions dues à l'exploitant pour la story 3.8

La migration accorde `UPDATE` à `internal_documents`, sans `DELETE`. Les tables
`internal_document_versions` et `internal_document_acknowledgements` restent en ajout seul :
`SELECT` et `INSERT` uniquement, sans `UPDATE` ni `DELETE`, avec déclencheurs MySQL bloquant toute
modification ou suppression. Après déploiement, joindre la sortie `SHOW GRANTS` et vérifier les
quatre déclencheurs `*_prevent_update` / `*_prevent_delete`.

## Actions dues à l'exploitant pour la story 2.2

Après la migration RBAC, exécuter les dix lignes `GRANT UPDATE` et les quatre lignes d'exception
`GRANT DELETE` des phases 3 et 4 sur la préproduction puis sur la production, avec le compte de
migration de chaque environnement. Joindre les sorties `SHOW GRANTS` horodatées au journal
d'exploitation. Vérifier explicitement que `DELETE` n'apparaît jamais sur `roles`, `permissions` ou
`role_has_permissions`.

## Actions dues à l'exploitant pour la story 2.1

Après déploiement des migrations, exécuter les quatre lignes de phase 3 ci-dessus sur la
préproduction puis sur la production, avec le compte de migration de chaque environnement. Joindre
les sorties `SHOW GRANTS` horodatées au journal d'exploitation. Aucun droit `DELETE` ne doit être
ajouté sur `people` ou `users`.

## Actions dues à l'exploitant pour la story 4.3

La migration de `expense_categories` accorde elle-même `UPDATE` au compte applicatif configuré.
Après déploiement, joindre la sortie `SHOW GRANTS` horodatée et vérifier que `expense_categories`
porte `SELECT`, `INSERT` et `UPDATE`, sans aucun `DELETE`.

## Actions dues à l'exploitant pour la story 4.4

Les migrations de `expenses` et `expense_approvals` accordent elles-mêmes `UPDATE` au compte
applicatif configuré. Après déploiement, joindre la sortie `SHOW GRANTS` horodatée et vérifier que
les deux tables portent `SELECT`, `INSERT` et `UPDATE`, sans aucun `DELETE`. Aucune colonne de
paiement n'est présente — ces colonnes seront ajoutées par migration en 8.6.
