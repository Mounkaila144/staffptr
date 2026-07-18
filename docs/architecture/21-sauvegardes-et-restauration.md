# 21. Sauvegardes et restauration

## 21.1 Objectifs

| Indicateur | Cible |
|---|---|
| RPO — perte maximale acceptée | **24 h** (NFR24) |
| RTO — délai de remise en service | **4 h** |
| Rétention | 7 quotidiennes, 4 hebdomadaires, 12 mensuelles |
| Conservation longue | 10 ans sur les données financières (NFR26, DEC-11) |

## 21.2 Dispositif

`spatie/laravel-backup`, tâche planifiée à **02 h 00 heure de Niamey** :

1. `mysqldump` de la base complète, **`--single-transaction`** — cohérent sans verrouiller
   l'application.
2. Archive de `storage/app/private` (pièces jointes).
3. Archive **chiffrée par mot de passe**, la clé étant conservée hors du serveur.
4. Envoi vers un **stockage objet hors site** (DEC-06).
5. Copie locale conservée 48 h pour restauration rapide.
6. **Notification d'échec** — et, plus important, **surveillance de l'absence de sauvegarde** :
   `backup:monitor` alerte quand la dernière sauvegarde est trop ancienne. Une sauvegarde qui cesse
   silencieusement est le mode de défaillance normal de ce dispositif.

> **DEC-06.** Les sauvegardes contiennent des données de personnel et des justificatifs financiers,
> et sortiront du Niger vers l'hébergeur retenu (Backblaze B2, Scaleway, OVH…). C'est une décision
> qui vous appartient, pas une décision technique. Le chiffrement côté application est appliqué
> quel qu'en soit le destinataire.

## 21.3 Test de restauration — NFR25

NFR25 est explicite : le test de restauration est **une tâche planifiée, pas une intention**.

Commande `php artisan ptr:test-restore`, exécutée **mensuellement** :

1. Récupère la dernière sauvegarde hors site.
2. La restaure dans une base jetable.
3. Vérifie des invariants : nombre de lignes d'audit, somme des encaissements, dernier utilisateur
   créé, présence des déclencheurs d'immuabilité.
4. **Écrit le résultat daté dans `docs/ops/restore-log.md`** (procédure et dernier résultat
   consignés, NFR25).
5. Alerte en cas d'échec.

Une **restauration complète manuelle en préproduction est exécutée et chronométrée avant chaque mise
en service d'étape**, pour valider le RTO de 4 h avec un opérateur humain dans la boucle.

## 21.4 Ce qui n'est pas sauvegardé

Redis (cache et files, reconstructibles), `storage/logs` (expédiés, § 22), `node_modules`, `vendor`.
**`.env` est sauvegardé séparément et manuellement**, hors du dispositif automatique, parce qu'il
contient les secrets et ne doit pas voyager avec les données.

---
