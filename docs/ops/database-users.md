# Comptes MySQL et matrice de privilèges

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

## Matrice applicative

| Catégorie ou table | `SELECT` | `INSERT` | `UPDATE` | `DELETE` |
|---|---:|---:|---:|---:|
| tables métier et financières des epics 2 à 8 | oui | oui | oui | **refusé** |
| `audit_logs` | oui | oui | **refusé** | **refusé** |
| `sessions` | oui | oui | oui | **accordé** |
| `jobs` | oui | oui | oui | **accordé** |
| `job_batches` | oui | oui | oui | **accordé** |
| `failed_jobs` | oui | oui | oui | **accordé** |
| `cache` | oui | oui | oui | **accordé** |
| `cache_locks` | oui | oui | oui | **accordé** |
| toute table créée ultérieurement | à décider explicitement | à décider explicitement | à décider explicitement | **refusé par défaut** |

Une migration qui crée une table métier doit accorder explicitement `SELECT, INSERT, UPDATE` au
compte applicatif lu depuis la configuration. Elle n'accorde jamais `DELETE` sans exception motivée,
revue et tracée dans ce document. `DELETE` sur `sessions` est obligatoire pour la révocation
immédiate des sessions suspendues ; Laravel supprime également les files, verrous et caches expirés.

La migration d'audit 1.4 reste l'unique source du `GRANT SELECT, INSERT` sur `audit_logs`. Elle lit
`AUDIT_DB_APP_USERNAME` et `AUDIT_DB_APP_HOST` depuis la configuration et ne code aucun compte en
dur. Le `GRANT OPTION` du compte de migration lui permet cette délégation sans sortir du schéma.

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

-- Phase 2 : droits applicatifs d'infrastructure de production
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_prod`.`sessions` TO 'ptrstaff_prod_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_prod`.`jobs` TO 'ptrstaff_prod_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_prod`.`job_batches` TO 'ptrstaff_prod_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_prod`.`failed_jobs` TO 'ptrstaff_prod_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_prod`.`cache` TO 'ptrstaff_prod_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_prod`.`cache_locks` TO 'ptrstaff_prod_app'@'localhost';

-- Phase 2 : droits applicatifs d'infrastructure de préproduction
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_staging`.`sessions` TO 'ptrstaff_staging_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_staging`.`jobs` TO 'ptrstaff_staging_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_staging`.`job_batches` TO 'ptrstaff_staging_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_staging`.`failed_jobs` TO 'ptrstaff_staging_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_staging`.`cache` TO 'ptrstaff_staging_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ptrstaff_staging`.`cache_locks` TO 'ptrstaff_staging_app'@'localhost';
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
3. les comptes applicatifs n'ont aucun `DELETE` sur une table métier ;
4. `audit_logs` n'accorde que `SELECT, INSERT` au compte applicatif ;
5. les six tables d'infrastructure accordent bien `DELETE` ;
6. seuls les comptes de migration disposent de `GRANT OPTION`.

