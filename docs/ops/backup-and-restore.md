# Sauvegarde

> Story 11.1, Task 2 (AC 1 à 12, AC 54). La restauration a son propre document :
> `docs/ops/restore-procedure.md`.

## 1. Ce qui est sauvegardé, et ce qui ne l'est pas

| Sauvegardé | Exclu | Pourquoi |
|---|---|---|
| Dump MariaDB `--single-transaction` | — | Cohérence : sans lui, un dump pris pendant un encaissement contiendrait la ligne de paiement sans son mouvement de compte. Restaurable **et faux**. |
| `storage/app/private` | — | Pièces jointes et justificatifs. |
| — | `.env` | **Sauvegarde manuelle séparée** (AC 7). Le joindre à l'archive reviendrait à ranger la clé dans le coffre qu'elle ouvre. |
| — | Redis | Cache et files : reconstruits, jamais de source de vérité. |
| — | `storage/logs` | Rétention propre de 30 jours ; les journaux n'ont rien à faire dans une archive conservée des années. |
| — | `vendor`, `node_modules` | Reconstruits par `composer install` et `npm ci`. |

## 2. Sauvegarde manuelle du `.env`

À refaire **à chaque modification** d'un secret :

```bash
# Depuis un poste d'exploitation, jamais stocké sur le serveur sauvegardé
scp root@<serveur>:/srv/staffptr/production/shared/.env ./env-production-$(date +%F)
gpg --symmetric --cipher-algo AES256 ./env-production-$(date +%F)
shred -u ./env-production-$(date +%F)
```

Le fichier chiffré rejoint le coffre de la direction, **hors du serveur et hors du stockage de
sauvegarde**. Trois copies au même endroit ne font qu'une seule copie.

## 3. Cadence et rotation

| Quoi | Quand | Rétention |
|---|---:|---|
| `backup:clean` | 01 h 45 Niamey | — |
| `backup:run` | **02 h 00 Niamey** (AC 1) | 7 quotidiennes, 4 hebdomadaires, 12 mensuelles (AC 5) |
| `backup:monitor` | 03 h 00 Niamey | Alerte si l'archive la plus récente a plus d'un jour |
| `ptr:test-restore` | 1ᵉʳ du mois, 04 h 00 | Résultat dans `shared/ops/restore-log.md` |

Le contrôle de fraîcheur tourne **après** la sauvegarde : le faire avant reviendrait à valider
celle de la veille.

## 4. Chiffrement (AC 3)

L'archive est chiffrée en AES-256 par la phrase secrète `BACKUP_ARCHIVE_PASSPHRASE`.

**La phrase secrète n'est jamais sur le serveur sauvegardé.** Sa copie de référence vit dans le
coffre de la direction. Celui qui prend le serveur ne doit pas prendre du même geste la clé de dix
ans d'archives.

## 5. Destination hors site — bloqué par DEC-06

| Variable | État |
|---|---|
| `BACKUP_OBJECT_ENDPOINT` | **Vide — DEC-06** |
| `BACKUP_OBJECT_BUCKET` | **Vide — DEC-06** |
| `BACKUP_OBJECT_ACCESS_KEY_ID` | **Vide — DEC-06** |
| `BACKUP_OBJECT_SECRET_ACCESS_KEY` | **Vide — DEC-06** |
| `BACKUP_ARCHIVE_PASSPHRASE` | **Vide — à générer avec DEC-06** |

Tant que `BACKUP_OBJECT_BUCKET` est vide, `config/backup.php` **ne déclare pas** la destination
hors site. Ce n'est pas un oubli : la déclarer produirait un échec quotidien qui noierait les vraies
alertes.

> **Conséquence à assumer.** Sans destination hors site, la sauvegarde protège d'une erreur
> humaine ou d'une corruption logique, **pas de la perte du serveur**. L'AC 54 n'est pas atteint et
> la première mise en production reste bloquée par DEC-06.

## 6. Conservation de dix ans — bloqué par DEC-11

Les données du personnel et les justificatifs financiers relèvent d'une conservation métier d'au
moins dix ans. **Elle n'est pas configurée ici** : la rotation ci-dessus conserve douze mois.

La régler par une rétention infinie sur le disque du VPS serait le moyen le plus sûr de saturer un
serveur partagé avec quatre autres applications. Le dimensionnement et le coût disque relèvent de
DEC-11.

**Ordre de grandeur à confirmer** : archive quotidienne chiffrée d'environ 50 Mo à un an
d'exploitation ⇒ ≈ 18 Go/an ⇒ ≈ 180 Go sur dix ans, soit **plus que le disque entier du VPS**
(72 Go). La conservation longue exige donc un stockage objet, pas le serveur.

## 7. RPO et vérification (AC 9)

**RPO ≤ 24 h** : au pire, on perd les écritures de la journée écoulée depuis 02 h 00.

Il se vérifie de trois façons, qui doivent concorder :

```bash
php artisan backup:list          # âge de l'archive la plus récente
php artisan ptr:check-invariants # invariant « fraîcheur < 26 h »
curl -s https://<domaine>/up | jq .checks.backup
```

Les 26 heures de l'invariant laissent deux heures de marge sur la sauvegarde de 02 h 00 : un
décalage d'ordonnanceur ne doit pas déclencher une fausse alerte, mais une exécution manquée doit
se voir dès le lendemain.

## 8. Ce que `/up` expose, et ce qu'il n'expose pas

```json
{ "checks": { "backup": { "state": "fraiche", "age_hours": 6, "max_age_hours": 26, "offsite_configured": false } } }
```

Ni chemin, ni fournisseur, ni bucket, ni phrase secrète : `/up` est **public**. Un attaquant ne doit
rien y apprendre sur l'emplacement des archives. Une sauvegarde périmée **dégrade** la santé sans
produire de 503 — l'application fonctionne, mais elle n'est plus protégée.
