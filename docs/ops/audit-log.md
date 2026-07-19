# Journal d'audit immuable

Le journal `audit_logs` est un composant ajout-seul. Une opération sensible appelle
`AuditLogger::runExplicitly()` à l'intérieur de sa transaction métier. L'écriture du modèle et celle
de l'audit utilisent la même connexion ; toute exception d'audit remonte et annule la transaction.
Le trait `Auditable` et `AuditableObserver` forment uniquement un filet de sécurité pour les
écritures Eloquent ayant contourné cet appel explicite.

## Barrières MySQL

La migration crée deux déclencheurs `BEFORE UPDATE` et `BEFORE DELETE` qui lèvent une erreur SQL.
Elle accorde au compte applicatif uniquement `SELECT` et `INSERT` sur `audit_logs`. Le compte de
migration doit être distinct, disposer des privilèges de schéma avec délégation, et utiliser une
instance MySQL autorisant la création de déclencheurs sans privilège global `SUPER`. La CI configure
pour cela `log_bin_trust_function_creators=1` avant d'exécuter la migration.

Les paramètres attendus par la migration sont :

- `AUDIT_DB_MIGRATION_CONNECTION` : nom de la connexion Laravel privilégiée ;
- `AUDIT_DB_APP_USERNAME` et `AUDIT_DB_APP_HOST` : compte auquel limiter la table ;
- les paramètres `DB_MIGRATION_*` de la connexion privilégiée.

Les comptes réels et leur matrice opposable sont définis dans
[`database-users.md`](database-users.md), et leur injection dans
[`environments.md`](environments.md). Les valeurs présentes dans le workflow de CI restent
exclusivement éphémères et conservent leur nommage `staffptr_*_ci`.

## Développement SQLite

SQLite crée la table, les colonnes et les index, mais la migration n'y simule ni privilèges MySQL ni
déclencheurs. Les tests locaux prouvent la garde modèle, la transaction, l'observateur et les
contrats de schéma compatibles. SQLite ne constitue jamais une preuve des deux barrières SQL : les
tests correspondants doivent passer sans être ignorés dans le job `PHPUnit (MySQL 8)`.

## Preuve CI

La [PR #2](https://github.com/Mounkaila144/staffptr/pull/2) exécute les trois barrières et la règle
bloquante 11. L'exécution
[29675237799](https://github.com/Mounkaila144/staffptr/actions/runs/29675237799) a validé 53 tests et
387 assertions sous MySQL 8, en 79 secondes sur un seuil strict de 600 secondes.
