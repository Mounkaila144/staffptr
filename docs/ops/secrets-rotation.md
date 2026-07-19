# Rotation des secrets

Toute rotation est consignée avec son déclencheur, son opérateur, son point de bascule, sa preuve et
son retour arrière. Une suspicion de compromission déclenche une rotation immédiate ; sinon, la
fréquence suit la politique de sécurité de l'entreprise.

## `ptrstaff_prod_app` et `ptrstaff_staging_app`

Déclencheurs : suspicion, départ d'un exploitant, exposition du `.env` ou échéance périodique.

1. générer un mot de passe aléatoire dans le magasin de secrets ;
2. exécuter `ALTER USER ... IDENTIFIED BY '<nouveau>' RETAIN CURRENT PASSWORD` avec le compte de
   migration du même environnement ;
3. remplacer `DB_PASSWORD` dans `shared/.env`, sans journaliser sa valeur ;
4. recharger le pool PHP-FPM 8.3 dédié ; c'est le point de bascule ;
5. vérifier `/up`, une lecture et une écriture applicatives ;
6. exécuter `ALTER USER ... DISCARD OLD PASSWORD` après observation.

Cette double validité MySQL 8 évite une interruption perceptible. Avant l'étape 6, le retour arrière
consiste à restaurer l'ancien `DB_PASSWORD` puis recharger PHP-FPM. Après l'étape 6, générer un
nouveau secret et reprendre la procédure. Vérifier aussi que chaque compte reste limité à son propre
schéma avec `SHOW GRANTS`.

## `ptrstaff_prod_migrate` et `ptrstaff_staging_migrate`

Déclencheurs : suspicion, exposition GitHub, changement d'exploitant ou échéance périodique.

1. suspendre les déploiements de l'environnement ;
2. générer la nouvelle valeur dans le magasin de secrets ;
3. conserver temporairement l'ancien mot de passe avec `RETAIN CURRENT PASSWORD` ;
4. mettre à jour `DB_MIGRATION_PASSWORD` dans le GitHub Environment concerné ;
5. lancer une vérification de connexion sans migration ; c'est le point de bascule ;
6. confirmer par `SHOW GRANTS` que `ALL PRIVILEGES` et `GRANT OPTION` restent bornés au bon schéma ;
7. supprimer l'ancien mot de passe et rouvrir les déploiements.

Avant l'étape 7, remettre l'ancien secret GitHub constitue le retour arrière. Ces identifiants ne
sont jamais copiés dans `shared/.env`.

## `APP_KEY`

Déclencheur normal : aucun. Une rotation n'est envisagée qu'après compromission confirmée ou
exigence réglementaire. `php artisan key:generate` ne constitue **pas** une procédure de rotation :
tant que des données sont chiffrées au repos, remplacer brutalement la clé les rend illisibles, de
même que les sessions existantes.

Avant toute bascule, inventorier les données chiffrées, concevoir et tester une migration de
déchiffrement avec l'ancienne clé puis rechiffrement avec la nouvelle, sauvegarder les deux clés hors
ligne pendant la fenêtre et obtenir une validation formelle. Le point de bascule n'arrive qu'après
preuve de lecture de toutes les données avec la nouvelle clé. Le retour arrière restaure l'ancienne
clé et la sauvegarde prise avant migration. En l'absence de cette procédure testée, **ne pas
tourner `APP_KEY`**.

Si une configuration devait exceptionnellement être versionnée, utiliser
`php artisan env:encrypt`; sa clé de déchiffrement reste hors dépôt et suit sa propre rotation.

## `DEPLOY_SSH_PRIVATE_KEY`

Déclencheurs : suspicion, clé perdue, changement de prestataire ou échéance périodique.

1. générer une nouvelle paire dédiée, sans réutiliser une clé personnelle ;
2. ajouter la clé publique à l'utilisateur `DEPLOY_USER`, sans accès `root`, avec les restrictions
   SSH prévues ;
3. remplacer `DEPLOY_SSH_PRIVATE_KEY` dans les GitHub Environments ;
4. vérifier une connexion et une commande de lecture ; c'est le point de bascule ;
5. supprimer l'ancienne clé publique, puis vérifier qu'elle est refusée.

Tant que l'ancienne clé publique est présente, restaurer l'ancien secret GitHub est le retour
arrière. Si les deux clés échouent, l'exploitant utilise l'accès console du VPS, jamais un accès
`root` ajouté à la clé de déploiement.

## Secrets de sauvegarde à venir

Les emplacements réservés sont `BACKUP_OBJECT_ENDPOINT`, `BACKUP_OBJECT_BUCKET`,
`BACKUP_OBJECT_ACCESS_KEY_ID`, `BACKUP_OBJECT_SECRET_ACCESS_KEY` et
`BACKUP_ARCHIVE_PASSPHRASE`. Ils restent sans valeur jusqu'à DEC-06 et la story 11.1.

La story 11.1 devra préciser pour chacun le déclencheur, l'ordre de rotation, la coexistence des
identifiants, le test d'envoi et de restauration, le point de bascule et le retour arrière. La
phrase secrète d'archive demeure hors du serveur. Une rotation n'est acceptée qu'après restauration
réussie d'une archive créée avec le nouveau secret ; l'ancien accès est conservé jusqu'à cette
preuve.

