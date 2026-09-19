# Procédure de restauration

> Story 11.1, Tasks 3 et 4 (AC 16, AC 18, AC 49, AC 55, AC 56). Procédure **versionnée** ici ;
> le registre d'exécution vit dans `shared/ops/restore-log.md`, hors de toute release.

**À lire en entier avant de commencer.** Une restauration se fait sous pression : ce n'est pas le
moment de découvrir qu'il manque une clé.

## 1. Prérequis

| Élément | Où il se trouve | Qui l'a |
|---|---|---|
| Phrase secrète d'archive (`BACKUP_ARCHIVE_PASSPHRASE`) | **Hors du serveur** — gestionnaire de secrets de la direction | Direction |
| Accès au stockage objet hors site | GitHub Environment `production` | Direction |
| Accès SSH au VPS | Clés d'exploitation | Exploitant |
| Identifiants MariaDB privilégiés | `shared/.env` du serveur | Exploitant |

> **La phrase secrète n'est jamais sur le serveur sauvegardé.** Une archive chiffrée dont la clé
> vit sur la machine qu'elle protège ne protège de rien : celui qui prend le serveur prend les deux.

## 2. Test automatisé mensuel

```bash
cd /srv/staffptr/production/current
php artisan ptr:test-restore
```

La commande récupère la dernière archive, la déchiffre, la restaure dans une base **jetable**
(`ptr_restore_test_<horodatage>`), vérifie les tables présentes, **détruit la base** et écrit le
résultat dans `shared/ops/restore-log.md`.

Elle **échoue bruyamment** : archive corrompue, clé invalide, stockage injoignable ou dump illisible
produisent un code d'erreur et une ligne `ÉCHEC` au registre. Un échec est une alerte, pas un
avertissement — il signifie qu'à cet instant, l'application **n'est pas protégée**.

## 3. Restauration complète manuelle — chronométrée

À exécuter **avant chaque mise en service de jalon** (AC 18, AC 49). Objectif : RTO ≤ 4 h.

**Démarrez le chronomètre maintenant.** Le RTO se mesure du moment où la décision de restaurer est
prise jusqu'au moment où l'application sert à nouveau — pas seulement la durée du `mysql <`.

### 3.1 Récupérer l'archive

```bash
cd /srv/staffptr/staging
php artisan backup:list                      # identifier l'archive à restaurer
# Télécharger depuis le stockage hors site vers un répertoire de travail à accès restreint
install -d -m 700 /root/restore-work
```

### 3.2 Vérifier et déchiffrer

> **`unzip` et Python ne savent pas ouvrir ces archives.** Le chiffrement est AES-256 ; les deux
> outils échouent avec un message trompeur (`need PK compat. v5.1`, `compression method is not
> supported`). Utilisez **PHP** ou `7z`. Constaté en production le 2026-08-17.

```bash
php8.3 -r '
$z = new ZipArchive; $z->open($argv[1]); $z->setPassword($argv[2]);
for ($i = 0; $i < $z->numFiles; $i++) {
    $n = $z->getNameIndex($i);
    if (str_ends_with($n, ".sql")) { file_put_contents($argv[3]."/dump.sql", $z->getFromName($n)); }
}
$z->close();' archive.zip "$PASSPHRASE" /root/restore-work
```

Un fichier de taille nulle signale une phrase secrète erronée : **arrêtez-vous** et vérifiez la
clé avant de toucher quoi que ce soit.

Si l'archive est illisible, **arrêtez-vous et prenez la précédente**. Restaurer une archive
douteuse par-dessus des données saines transforme un incident en désastre.

### 3.3 Restaurer dans une base jetable, jamais par-dessus

```bash
# Les clauses DEFINER doivent être retirées : recréer un déclencheur au nom du compte qui l'a
# créé exige le privilège SUPER, que le rôle de restauration ne porte pas — et ne doit pas porter.
sed -E 's#/\*!5000[0-9] DEFINER=[^*]+\*/##g; s#DEFINER=`[^`]*`@`[^`]*`##g' \
  /root/restore-work/dump.sql > /root/restore-work/clean.sql

mysql -e "CREATE DATABASE ptr_restore_manual CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --user=staffptr_restore --password="$RESTORE_PW" ptr_restore_manual < /root/restore-work/clean.sql
```

> **Utilisez le client `mysql`, pas un script PHP/PDO.** Le dump contient des directives
> `DELIMITER` — instructions du client, pas du SQL — que PDO rejette. L'import échouerait
> précisément sur les déclencheurs, c'est-à-dire sur ce que la restauration doit le plus sûrement
> rétablir.

> **Ne restaurez jamais directement sur `staffptr_production`.** On restaure à côté, on vérifie,
> puis on bascule. Écraser la base en place supprime la seule chose qui pourrait sauver la
> situation si l'archive s'avère incomplète.

### 3.4 Contrôler

```bash
mysql -e 'SELECT COUNT(*) AS tables FROM information_schema.tables
          WHERE table_schema="ptr_restore_manual";'                      # attendu : 80
mysql -e 'SELECT COUNT(*) AS declencheurs FROM information_schema.triggers
          WHERE trigger_schema="ptr_restore_manual";'                    # attendu : 32
mysql -e 'SELECT trigger_name FROM information_schema.triggers
          WHERE trigger_schema="ptr_restore_manual" AND event_object_table="audit_logs";'
mysql ptr_restore_manual -e "SELECT COUNT(*) FROM audit_logs;"
```

**Le contrôle des déclencheurs est le plus important.** Une base restaurée sans eux fonctionne en
apparence et a perdu ses barrières d'immuabilité : on pourrait y modifier le journal d'audit. Les
deux déclencheurs `audit_logs_prevent_update` et `audit_logs_prevent_delete` doivent être présents.

Un journal d'audit vide **alors que d'autres tables portent des données** signale une restauration
partielle : **n'allez pas plus loin**. Sur une installation neuve, tout est vide et c'est normal.

### 3.5 Restaurer les pièces jointes

```bash
unzip archive.zip 'storage/app/private/*' -d /srv/staffptr/staging/shared/
chown -R staffptr:www-data /srv/staffptr/staging/shared/storage
```

### 3.6 Anonymiser si la cible est la préproduction

```bash
cd /srv/staffptr/staging/current
php artisan ptr:anonymize --force
```

### 3.7 Détruire la base de travail

```bash
mysql -e "DROP DATABASE ptr_restore_manual;"
rm -rf /root/restore-work
```

**Ne sautez pas cette étape.** Une base de restauration oubliée sur un VPS partagé est une copie
complète des données personnelles, hors de tout contrôle d'accès applicatif.

### 3.8 Consigner

**Arrêtez le chronomètre** et ajoutez une ligne à `shared/ops/restore-log.md` :

```
| 2026-08-17 14:30 | RÉUSSI | Restauration manuelle chronométrée, jalon 1 | durée : 1 h 47 · opérateur : … · archive : … |
```

Le RTO n'est validé **qu'avec une durée réelle mesurée ≤ 4 h**. Une estimation ne vaut rien.

## 4. Escalade

Si la restauration échoue ou dépasse 4 h :

1. Prévenir le contact d'astreinte (`docs/ops/incident.md`).
2. Prendre l'archive de la veille et recommencer.
3. Si deux archives consécutives échouent, c'est le **dispositif de sauvegarde** qui est en cause,
   pas l'archive : traiter comme un incident majeur.

## 5. Conflit documentaire connu

L'architecture § 21.3 indique encore `docs/ops/restore-log.md`. Le PRD, plus récent, impose
`shared/ops/restore-log.md`, et il a raison : un registre écrit dans une release disparaît à la
rotation, précisément quand on voudrait prouver qu'une restauration a été testée il y a six mois.
**Cette procédure suit le PRD.** L'architecture doit être réalignée.
