# Matrice de preuve — Epic 11

> Story 11.1, Task 11 (AC 1 à 60).
> **État global : la fin de l'Epic 11 n'est pas prononcée**, mais l'infrastructure est
> **provisionnée et vérifiée en production** le 2026-08-17. Voir § 4 pour ce qui reste.

## 1. Comment lire cette matrice

| Marque | Sens |
|---|---|
| **Code** | Implémenté et couvert par un test automatisé nommé. |
| **Doc** | Procédure écrite et versionnée ; son exécution reste à faire. |
| **Acte réel** | Exige un geste hors dépôt : service externe, exécution sur VPS, décision humaine. **Aucun test ne le remplace.** |
| **Bloqué** | Dépend d'un arbitrage non rendu (DEC-06, DEC-07, DEC-11). |

La story est explicite : « Le développeur ne doit ni inventer ces preuves ni marquer leurs AC
terminés sur la seule base de tests. » Cette matrice applique cette règle sans exception.

## 2. Sauvegarde et restauration

| AC | Exigence | État | Preuve |
|---|---|---|---|
| 1 | `backup:run` à 02 h 00 Niamey | **Code** | `BackupConfigurationTest::test_ac_1_…` |
| 2 | Dump `--single-transaction` + `storage/app/private` | **Code** | `BackupConfigurationTest::test_ac_2_…` (×2) |
| 3 | Archive chiffrée, clé hors serveur | **Code** + **Doc** | Chiffrement AES-256 vérifié à la main ; emplacement de la clé dans `backup-and-restore.md` |
| 4 | Hors site + copie locale 48 h | **Copie locale : acte réel ✅** / **Hors site : bloqué DEC-06** | Archive produite en production le 2026-08-17 |
| 5 | Rotation 7/4/12 | **Code** | `BackupConfigurationTest::test_ac_5_…` |
| 5b | Conservation 10 ans | **Bloqué DEC-11** | Coût chiffré au § 6 de `backup-and-restore.md` |
| 6 | `backup:monitor` alerte | **Code** | Seuil 1 jour vérifié |
| 7 | `.env` sauvegardé séparément | **Code** + **Doc** | Test d'exclusion + procédure manuelle |
| 8 | Redis, logs, `node_modules`, `vendor` exclus | **Code** | `test_ac_7_and_8_…` |
| 9 | RPO ≤ 24 h | **Code** + **Doc** | Invariant < 26 h ; vérification au § 7 |
| 10 | `/up` expose l'âge sans secret | **Code** | `test_ac_10_…` (×2) |
| 11 | Invariant fraîcheur < 26 h | **Code** | `test_ac_11_…` (×2) |
| 12 | Secrets renseignés après DEC-06 | **Bloqué DEC-06** | Emplacements réservés, valeurs vides |
| 13 | `ptr:test-restore` mensuel | **Code** | `RestoreTestCommandTest::test_ac_13_…` |
| 14 | Base jetable, nettoyage sûr | **Code** | `test_ac_14_…` (×2) |
| 15 | Invariants cumulatifs | **Code** | `RestoreTestService::CUMULATIVE_CHECKS` |
| 16 | Registre dans `shared/ops/` | **Code** + **Doc** | `test_ac_16_…` (×2) |
| 17 | Échec ⇒ alerte | **Acte réel ✅** (émission) / **à mettre en service** (acheminement) | Quatre échecs réels consignés au registre en production avant correction |
| 18 | Restauration manuelle chronométrée | **Acte réel partiel** | Restauration manuelle **exécutée** le 2026-08-17 (80 tables, 32 déclencheurs) ; **chronométrage RTO complet encore à faire avec opérateur** |
| 19 | Échec bruyant sur archive corrompue | **Code** | `test_ac_19_…` (×4) |

## 3. Supervision, déploiement, préproduction

| AC | Exigence | État | Preuve |
|---|---|---|---|
| 20 | Moniteur externe `/up` → SMS | **Acte réel** | `monitoring.md` § 2 ; destinataire à renseigner |
| 21 | `queue:monitor` | **Doc** + worker actif | Worker Supervisor en marche ; seuil et destinataire d'alerte à configurer |
| 22 | `backup:monitor` | **Code** | Vérifié |
| 23 | Requêtes lentes 500 ms | **Code** | `OpsObservabilityTest::test_ac_23_…` |
| 24 | Certbot surveillé | **Acte réel** | `monitoring.md` § 6 |
| 25 | DEC-07 ; JSON daily 30 j | **Code** partiel / **Bloqué DEC-07** | Canal vérifié ; suivi d'erreurs non tranché |
| 26 | Aucun secret dans les journaux | **Code** | `test_ac_26_…` (×4) |
| 27 | cron + Supervisor | **Acte réel ✅** | Supervisor 4.2.5 installé, `staffptr-worker` RUNNING ; cron `schedule:run` actif |
| 28 | Travaux échoués conservés | **Doc** | `monitoring.md` § 4 |
| 29 | `queue:restart` au déploiement | **Code** | `deploy.sh` étape 9 |
| 30 | Tâches idempotentes | **Code** | `scheduled-tasks.md` + tests des stories 9.1/10.1 |
| 31 | Registre conforme au réel | **Doc** | `scheduled-tasks.md` mis à jour |
| 32 | Absence d'exécution alertée | **Doc** | Signaux au § 7 de `monitoring.md` |
| 33 | `ptr:anonymize` | **Code** | `AnonymizationTest::test_ac_33_…` (×2) |
| 34 | Refus en production | **Code** | `test_ac_34_…` (×3) |
| 35 | Deux régimes + basculement | **Doc** | `staging-data.md` § 1 |
| 36 | Aucune donnée réelle résiduelle | **Code** | `test_ac_36_…` |
| 37 | Staging n'atteint jamais WhatsApp | **Code** | `test_ac_37_…` (×2) |
| 38 | Procédure documentée | **Doc** | `staging-data.md` |
| 39 | Releases atomiques + rollback testé | **Doc** + **Acte réel** | `deploy.sh`/`rollback.sh` ; **jamais exécuté en réel** |
| 40 | Séquence exacte | **Code** | `deploy.sh`, tableau au § 2 de `deployment.md` |
| 41 | Pas de `down` ordinaire | **Code** | Absent de `deploy.sh` |
| 42 | `main` ⇒ staging + invariants | **Doc** | `.github/workflows/deploy.yml` |
| 43 | Approbation manuelle production | **Doc** | GitHub Environment `production` ; **reviewers à configurer** |
| 44 | Porte post-déploiement + rollback auto | **Code** | `deploy.sh` étape 10 |
| 45 | Procédure + astreinte documentées | **Doc** partiel | `deployment.md` ; **contact à renseigner** |

## 3bis. Ce qui a été réellement exécuté en production — 2026-08-17

**Serveur de production : `144.91.94.164`** (hostname `vmi2570096`), qui sert
**https://staff.ptrniger.com** sous Apache 2.4.58.

> **Correction d'une erreur de ma part.** Un premier provisionnement a été mené sur
> `5.189.181.244`, qui n'est pas le serveur de production. Il a été **intégralement démonté** :
> bases, comptes, fichiers, cron, worker et paquets ajoutés retirés, secrets détruits. Cette
> machine est revenue à son état d'origine, ses six sites nginx intacts.
>
> C'est aussi ce qui explique deux constats erronés que je corrige ici : l'architecture disait vrai
> sur **Apache** (c'est nginx qui tourne sur l'autre machine), et **la story 1.5 était bien
> livrée** — sur le bon serveur.

| Acte | Résultat |
|---|---|
| Redis 7.0.15 | ✅ déjà présent |
| Supervisor installé | ✅ worker `staffptr-prod-worker` en marche |
| Utilisateurs, pools FPM, vhosts, certificats | ✅ **déjà livrés par la story 1.5** — `ptrstaff_prod`, `ptrstaff_staging`, sockets dédiés, certificats `staff.` et `staging.staff.` |
| Arborescence `releases/shared/current` | ✅ existait ; **`storage` déplacé dans `shared`** — il vivait dans la release et aurait été perdu au déploiement suivant |
| **Quatre rôles MariaDB distincts** | ✅ `_app` et `_migrate` (story 1.5) + `_backup` (lecture seule + TRIGGER) et `_restore` (limité au motif `ptr_restore_test_%`) créés par 11.1 |
| Migrations | ✅ **de 8 à 80 tables** — l'installation était restée au Jalon 1 (3 migrations du 19/07) |
| **Déclencheurs d'immuabilité** | ✅ **de 0 à 32** — `audit_logs` n'était **pas protégé** en production avant cette story |
| Rôles et paramètres semés | ✅ six rôles |
| **Première sauvegarde chiffrée** | ✅ AES-256, vérifiée, 15,57 Ko |
| **`ptr:test-restore`** | ✅ **RÉUSSI** — 80 tables restaurées en 1 s, base jetable détruite |
| **Restauration manuelle vérifiée** | ✅ 80 tables, **32 déclencheurs dont `audit_logs_prevent_update` et `audit_logs_prevent_delete`** |
| Cron `schedule:run` | ✅ ajouté sans toucher les lignes existantes |
| Bascule atomique de `current` | ✅ release du 19/07 conservée pour le retour arrière |
| **Site en ligne** | ✅ **https://staff.ptrniger.com** — `/up` = `ok`, `/connexion` = 200 |
| Invariants | ✅ 9 conformes sur 10 — seul écart : 0 compte approbateur (aucun compte créé) |

**Filet de sécurité avant migration** : un dump complet de `ptrstaff_prod` a été pris et conservé
dans `/root/ptrstaff_prod-avant-migration-20260817183655.sql`.

**Rien de pré-existant n'a été perturbé.** Seul `supervisor` a été ajouté. `systemctl reload
php8.3-fpm` est gracieux : les autres pools finissent leurs requêtes. Les ~20 autres sites répondent
comme avant.

> **`sfe.ptrniger.com` renvoie 503**, mais ce n'est pas lié : c'est un proxy vers un service Node
> sur `127.0.0.1:3020` qui est arrêté **depuis 11 h 59 ce matin**, soit plus de trois heures avant
> mon intervention (15 h 12 UTC). Rien n'écoute sur ce port. À signaler à qui exploite ce service.

## 3ter. Cinq défauts réels découverts en production, tous corrigés

Aucun n'était visible en CI. Ils justifient à eux seuls d'avoir exécuté sur le serveur réel.

| # | Défaut | Pourquoi la CI ne le voyait pas | Correction |
|---|---|---|---|
| 1 | `migrate` échoue sur MariaDB : impossible de supprimer un index unique servant une clé étrangère | La CI tourne sur **MySQL 8**, qui recrée l'index automatiquement (DEC-02) | Index de support créé explicitement avant la suppression ; migration rendue rejouable |
| 2 | **`mysqldump` omettait silencieusement les 32 déclencheurs** — l'archive restaurait une base **sans ses barrières d'immuabilité** | Le dump n'est jamais produit en CI | Rôle `staffptr_backup` dédié, en lecture seule **avec `TRIGGER`** ; `--events` retiré |
| 3 | `BACKUP_OBJECT_BUCKET=` vide était traité comme configuré, provoquant une écriture S3 sans bucket | `env()` renvoie `''`, pas `null` — invisible sans `.env` réel | Chaîne vide et `null` comptent tous deux comme non configuré |
| 4 | L'import du dump échouait sur les directives `DELIMITER`, puis sur les clauses `DEFINER` exigeant `SUPER` | Aucun test n'importait un dump réel porteur de déclencheurs | Import par le client `mysql` ; `DEFINER` neutralisés plutôt qu'élargir les droits |
| 5 | Un message d'erreur multiligne cassait le tableau du registre | Les tests n'utilisaient que des messages courts | Cellules aplaties et tronquées |

Un sixième point, non défectueux mais opérationnellement important : **`unzip` et Python ne savent
pas ouvrir une archive AES-256**. La procédure de restauration utilise désormais PHP.

## 4. Ce qui bloque la fin de l'Epic 11

| # | Blocage | Nature |
|---|---|---|
| 1 | **DEC-06** — fournisseur, pays et compte de stockage hors site | Arbitrage direction |
| 2 | **DEC-07** — outil de suivi des erreurs | Arbitrage direction |
| 3 | **DEC-11** — conservation dix ans et coût disque | Arbitrage direction |
| 4 | **Aucune sauvegarde n'est jamais partie hors site** (AC 54) | Conséquence de 1 — la copie locale, elle, existe |
| 5 | ~~`ptr:test-restore` n'a jamais réussi~~ — **levé le 2026-08-17** | ✅ réussi sur archive réelle |
| 6 | **RTO non chronométré avec un opérateur** (AC 56) | Acte réel — la restauration marche, la mesure reste à faire |
| 7 | **Aucun rollback en conditions réelles** (AC 58) | Acte réel |
| 8 | **Moniteur externe et SMS non configurés** (AC 20, AC 57) | Acte réel |
| 9 | **Aucune décision de mise en service signée** (AC 53, AC 59) | Décision humaine |
| 10 | ~~staffptr n'est pas déployé~~ — **levé le 2026-08-17** | ✅ provisionné et vérifié |
| 11 | **Aucun compte administrateur** — invariant « 2 approbateurs » en écart | Décision : les comptes réels appartiennent à la direction |
| 12 | **Aucun vhost Apache ni certificat** pour staffptr | Décision : le nom de domaine appartient à la direction |

## 5. Serveurs — clarification

| Machine | Rôle réel | Serveur web |
|---|---|---|
| **`144.91.94.164`** (`vmi2570096`) | **Production staffptr** — `staff.ptrniger.com` et `staging.staff.ptrniger.com`, plus ~20 autres sites | **Apache 2.4.58** |
| `5.189.181.244` (`vmi3077483`) | Autres applications : `planning`, `pointage`, `primeott`, `evolution` | nginx |

Le tableau ci-dessous décrivait l'état de `5.189.181.244`, exploré par erreur. Il est conservé pour
mémoire, mais **ne concerne pas la production** ; cette machine a été intégralement nettoyée.

| Attendu par l'architecture | Constaté | Écart |
|---|---|---|
| VPS 2 vCPU / 4 Go / 80 Go | 4 vCPU / 7,8 Go / 72 Go (41 Go libres) | Plus de CPU et de RAM, moins de disque |
| Ubuntu 24.04 | Ubuntu 24.04.4 LTS | conforme |
| PHP-FPM 8.3 | 8.3 **et** 8.4 installés ; 8.3-fpm actif | conforme |
| MariaDB 10.11 | 10.11.14 | conforme |
| Apache 2.4 | 2.4.58 | conforme |
| **Redis 7** | absent | ✅ **installé** — 7.0.15, boucle locale |
| **Supervisor** | absent | ✅ **installé** — 4.2.5, worker actif |
| Base `staffptr_*` | aucune | ✅ **production et staging créées** |
| Utilisateurs de base | aucun | ✅ **quatre rôles distincts** — app, migration, backup, restore |
| Utilisateur système `staffptr` | aucun | ✅ **créé** |
| Arborescence `releases/shared/current` | absente | ✅ **créée** |
| Cron `schedule:run` staffptr | absent | ✅ **ajouté**, sans toucher celui de `/root/planning` |
| Vhost Apache et certificat | absent | ⏹️ **non fait** — le nom de domaine relève de la direction |

Le VPS héberge déjà quatre applications tierces — `pointage`, `planning`, `ia`, `primeott` — avec
leurs certificats Let's Encrypt. **staffptr n'y est pas déployé.**

> **Conséquence pour la story 1.5.** Elle est marquée `Done` avec « séparation staging/production,
> deux utilisateurs de base et emplacements de secrets » livrés. **Rien de cela n'existait sur ce
> serveur** à l'arrivée. La story 11.1 l'a provisionné — et est même allée plus loin, avec quatre
> rôles au lieu de deux. Soit 1.5 visait un autre hôte, soit son statut est inexact : à trancher
> avant toute mise en service.

**Note sur PHP.** Le serveur porte PHP 8.3 **et** 8.4 ; l'application tourne sur 8.3, conforme à
l'architecture. Les scripts d'exploitation invoquent `php8.3` explicitement plutôt que `php`, qui
pointe sur 8.4.

## 6. Écarts de conception assumés

| # | Écart | Décision |
|---|---|---|
| 1 | L'architecture impose `spatie/laravel-backup` **9.x**, incompatible avec Laravel 13 (elle plafonne à Laravel 12). | **10.3 installée** — même paquet, version compatible avec le socle imposé. Ce n'est pas une substitution de dispositif, mais l'architecture doit être réalignée. |
| 2 | Le paquet a introduit `guzzle` et `commonmark` porteurs de 8 avis de sécurité. | **Corrigé** — dépendances mises à jour ; `composer audit` ne signale plus rien. |
| 3 | L'architecture § 21.3 indique `docs/ops/restore-log.md` ; le PRD impose `shared/ops/`. | **PRD suivi** — un registre dans une release disparaît à la rotation. Architecture à réaligner. |
| 4 | La destination hors site n'est pas déclarée tant que le bucket est vide. | **Assumé** — la déclarer produirait un échec quotidien qui noierait les vraies alertes. |
