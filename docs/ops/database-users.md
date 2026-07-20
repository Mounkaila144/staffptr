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
GRANT UPDATE ON `ptrstaff_staging`.`people` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`users` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`roles` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`permissions` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`model_has_roles` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`model_has_permissions` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`role_has_permissions` TO 'ptrstaff_staging_app'@'localhost';
GRANT UPDATE ON `ptrstaff_staging`.`login_attempts` TO 'ptrstaff_staging_app'@'localhost';

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
6. les six tables d'infrastructure accordent bien `UPDATE, DELETE` ;
7. `people` et `users`, puis chaque future table métier, portent leur ligne `GRANT UPDATE`, conformément à la consigne
   permanente en tête de ce document ;
8. seuls les comptes de migration disposent de `GRANT OPTION`.

## Actions dues à l'exploitant pour la story 2.6

Après la migration de `login_attempts`, exécuter les deux lignes `GRANT UPDATE` de phase 3 sur la
préproduction puis sur la production avec le compte de migration de chaque environnement. Joindre
les sorties `SHOW GRANTS` horodatées au journal d'exploitation et vérifier explicitement que
`DELETE` reste absent sur `login_attempts`.

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
