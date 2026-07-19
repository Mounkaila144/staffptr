# Environnements, préproduction et secrets de déploiement

Ce runbook décrit le contrat d'exploitation livré par la story 1.5. Il ne prouve pas l'état du
VPS : l'exploitant doit exécuter puis consigner les contrôles indiqués ci-dessous.

## Les quatre environnements

| Environnement | Emplacement et URL | Base | `APP_DEBUG` | Files | Courriel | Données | Sauvegarde |
|---|---|---|---|---|---|---|---|
| Local | poste développeur, `http://localhost` | SQLite locale | `true` | synchrones | `log` | fictives | aucune |
| CI | conteneurs GitHub Actions, sans URL publique | MySQL 8 `staffptr_test` éphémère | `false` | synchrones | `array` | tests | aucune |
| Préproduction | VPS partagé, `https://staging.staff.ptrniger.com` | MySQL dédiée `ptrstaff_staging` | `false` | Redis | `log`, aucun envoi | restauration de production anonymisée | aucune |
| Production | VPS partagé, `https://staff.ptrniger.com` | MySQL dédiée `ptrstaff_prod` | `false` | Redis | `log`, aucun envoi en MVP | réelles | quotidienne, story 11.1 |

La préproduction possède un hôte virtuel, un utilisateur système, un pool PHP-FPM 8.3 et une base
distincts. Son rôle est de valider les migrations **et les restaurations**, en plus des
fonctionnalités. Jusqu'à la story 11.5 elle démarre avec une base migrée sans données de production.
À partir de 11.5, elle est alimentée par restauration d'une sauvegarde de production, puis par
`ptr:anonymize` avant toute utilisation applicative.

Aucun `.env.staging` ou `.env.production` n'est versionné. Les valeurs non secrètes attendues sont :

```dotenv
# Préproduction — à reporter dans shared/.env, jamais dans le dépôt
APP_ENV=staging
APP_URL=https://staging.staff.ptrniger.com
APP_DEBUG=false
DB_DATABASE=ptrstaff_staging
DB_USERNAME=ptrstaff_staging_app
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database
MAIL_MAILER=log
REDIS_PREFIX=ptrstaff_staging_
REDIS_DB=10
REDIS_CACHE_DB=11
AUDIT_DB_APP_USERNAME=ptrstaff_staging_app
AUDIT_DB_APP_HOST=localhost
```

```dotenv
# Production — à reporter dans shared/.env, jamais dans le dépôt
APP_ENV=production
APP_URL=https://staff.ptrniger.com
APP_DEBUG=false
DB_DATABASE=ptrstaff_prod
DB_USERNAME=ptrstaff_prod_app
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database
MAIL_MAILER=log
REDIS_PREFIX=ptrstaff_prod_
REDIS_DB=12
REDIS_CACHE_DB=13
AUDIT_DB_APP_USERNAME=ptrstaff_prod_app
AUDIT_DB_APP_HOST=localhost
```

Les index Redis 10/11 sont réservés à la préproduction et 12/13 à la production dans l'inventaire
du VPS. L'exploitant vérifie cette réservation avant provisionnement. Le préfixe est explicite et
figé : il ne dépend jamais de `APP_NAME`. Sans le préfixe et les index dédiés, un
`php artisan cache:clear` peut vider le cache des autres projets du serveur.

## DEC-05 — VPS existant partagé

La préproduction et la production résident sur le VPS existant, déjà partagé avec d'autres
projets. Cette décision du 19/07/2026 est tranchée ; ses conséquences ne sont pas des risques
facultatifs :

1. la compromission d'un autre projet ouvre un chemin vers la même instance MySQL et potentiellement
   vers `shared/.env`, ce qui affaiblit le modèle de menace du journal d'audit ;
2. `log_bin_trust_function_creators = 1` relâche un contrôle globalement pour toutes les bases ;
3. Redis est partagé et impose des préfixes ainsi que des index distincts ;
4. le disque est partagé entre la rétention dix ans, les sauvegardes et les autres projets ;
5. la contagion peut partir de n'importe quel projet du serveur vers n'importe quel autre.

Les quatre mesures suivantes sont non négociables :

| Mesure obligatoire | Ce qu'elle empêche concrètement |
|---|---|
| utilisateur système PTR Staff dédié | un autre projet ne peut pas lire `shared/.env` avec ses droits Unix |
| pool PHP-FPM 8.3 dédié exécuté sous cet utilisateur | le code PHP d'un autre projet n'hérite pas de l'identité PTR Staff |
| `REDIS_PREFIX`, `REDIS_DB` et `REDIS_CACHE_DB` réservés par environnement | les commandes de cache et de file ne touchent pas aux clés voisines |
| surveillance de l'espace disque | une sauvegarde, la rétention dix ans NFR26 ou un autre projet ne remplit pas silencieusement le volume |

La surveillance doit couvrir la rétention dix ans, les sauvegardes quotidiennes avec copie locale
48 heures et la consommation des autres projets. Son implémentation appartient à la story 11.3.

**Condition de révision :** le provisionnement reste paramétré par hôte et par schéma ; migrer vers
un VPS dédié ne doit changer que des valeurs, jamais du code. Si l'entreprise croît ou si un litige
rend l'opposabilité du journal critique, DEC-05 doit être la première décision rouverte.

## Acte d'exploitation MySQL global

`log_bin_trust_function_creators = 1` est exigé par les déclencheurs d'immuabilité du journal. Sur
le conteneur CI jetable, sa portée disparaît avec le conteneur. Sur le VPS partagé, c'est un réglage
`GLOBAL` qui concerne aussi les autres projets. Avant la première migration, l'exploitant doit :

1. annoncer la fenêtre et relever la valeur actuelle ;
2. faire valider l'impact sur les autres bases ;
3. appliquer le réglage avec un compte d'administration, hors du script de provisionnement PTR Staff ;
4. consigner la date, l'opérateur, l'ancienne et la nouvelle valeur dans le journal d'exploitation ;
5. vérifier la création des deux déclencheurs `audit_logs_prevent_update` et
   `audit_logs_prevent_delete`.

Cet acte séparé est assumé par DEC-05. Il ne confère aucun privilège global aux comptes PTR Staff.

Sur MariaDB (DEC-12, instance du VPS partagé), `SET PERSIST` n'existe pas : appliquer
`SET GLOBAL log_bin_trust_function_creators = 1;` puis persister le réglage dans un fichier de
`/etc/mysql/mariadb.conf.d/` (section `[mysqld]`), sans redémarrage.

## Contrat des secrets GitHub Environments

Les environnements GitHub `staging` et `production` portent chacun les mêmes noms de secrets, avec
des valeurs propres à leur cible. La story 11.6 consommera ce contrat :

| Secret | Usage |
|---|---|
| `DEPLOY_HOST` | hôte SSH de l'environnement |
| `DEPLOY_PORT` | port SSH |
| `DEPLOY_USER` | utilisateur système dédié, jamais `root` |
| `DEPLOY_SSH_PRIVATE_KEY` | clé privée de déploiement dédiée, distincte des clés personnelles |
| `DB_MIGRATION_HOST` | hôte MySQL local injecté dans le processus de migration |
| `DB_MIGRATION_PORT` | port MySQL injecté dans le processus de migration |
| `DB_MIGRATION_DATABASE` | `ptrstaff_staging` ou `ptrstaff_prod` |
| `DB_MIGRATION_USERNAME` | compte `…_migrate` de l'environnement |
| `DB_MIGRATION_PASSWORD` | mot de passe du compte `…_migrate` |

La clé SSH est limitée à `DEPLOY_USER`, sans accès `root`, et n'est utilisée par aucune personne.
Le fichier `shared/.env` ne contient jamais de variable `DB_MIGRATION_*`. Lors d'une release, le
futur workflow 11.6 fournit ces variables uniquement au processus :

```text
php artisan migrate --force --database=mysql_migration
```

Elles n'existent que pendant ce processus, sont retirées immédiatement après, ne sont jamais
écrites sur disque et ne passent jamais par `php artisan config:cache`. Les variables permanentes
`AUDIT_DB_APP_USERNAME` et `AUDIT_DB_APP_HOST=localhost` permettent à la migration 1.4 de déléguer
`SELECT, INSERT` sur `audit_logs`. Le compte de migration possède pour cela `GRANT OPTION`, borné à
son seul schéma.

## Protection de `shared/.env` et d'`APP_KEY`

À l'installation, l'exploitant crée `shared/.env`, le donne à l'utilisateur système PTR Staff et
applique `chmod 600`. Il vérifie avec `stat` le propriétaire, le groupe et les permissions
effectives, puis confirme depuis les utilisateurs et pools PHP-FPM des autres projets que la lecture
est refusée. Sur ce serveur partagé, cette vérification est une frontière du modèle de menace.

`APP_KEY` est générée une seule fois avec `php artisan key:generate`, puis sauvegardée hors ligne
dans le coffre d'entreprise. Sa perte rend définitivement illisibles les sessions et les données
chiffrées au repos, même si une sauvegarde de base valide existe. `shared/.env` est sauvegardé
séparément et manuellement ; il ne voyage jamais avec les sauvegardes automatiques de données.

## Emplacements réservés pour la sauvegarde

Les secrets suivants seront créés dans le GitHub Environment `production`, sans valeur pour le
moment : `BACKUP_OBJECT_ENDPOINT`, `BACKUP_OBJECT_BUCKET`, `BACKUP_OBJECT_ACCESS_KEY_ID`,
`BACKUP_OBJECT_SECRET_ACCESS_KEY` et `BACKUP_ARCHIVE_PASSPHRASE`.

DEC-06 doit d'abord être arbitré par la direction, car le choix du stockage objet peut faire sortir
les données du Niger. Seule la story 11.1 est habilitée à renseigner ces valeurs et à installer
`spatie/laravel-backup`. La phrase secrète de chiffrement d'archive restera hors du serveur. Aucun
compte, paquet ou planification de sauvegarde n'est créé par la story 1.5.

