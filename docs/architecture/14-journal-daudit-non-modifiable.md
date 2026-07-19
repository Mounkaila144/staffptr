# 14. Journal d'audit non modifiable

## 14.1 Trois barrières — A-05

Le PRD exige qu'aucune interface ne permette de modifier ou supprimer une entrée d'audit (FR22).
L'exigence est tenue **au-delà de l'interface**, parce qu'une garantie qui repose uniquement sur
l'absence de bouton n'en est pas une :

| # | Barrière | Portée |
|---|---|---|
| 1 | **Privilèges SQL** — l'utilisateur MySQL applicatif ne détient ni `UPDATE` ni `DELETE` sur `audit_logs` | Bloque même une injection SQL réussie |
| 2 | **Déclencheurs base** — `BEFORE UPDATE` et `BEFORE DELETE` qui `SIGNAL` une erreur | Bloque une console d'administration, un correctif manuel |
| 3 | **Trait applicatif** `Immutable` — `update()` et `delete()` lèvent une exception | Bloque l'erreur de programmation, tôt et lisiblement |

```sql
-- Le nom du compte dépend de l'environnement (voir § 25.4). La migration le lit depuis
-- la configuration (AUDIT_DB_APP_USERNAME), il n'est jamais codé en dur.
GRANT SELECT, INSERT ON ptrstaff_prod.audit_logs TO 'ptrstaff_prod_app'@'localhost';
-- ni UPDATE ni DELETE : volontaire

CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs
FOR EACH ROW SIGNAL SQLSTATE '45000'
  SET MESSAGE_TEXT = 'audit_logs est en ajout seul';

CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs
FOR EACH ROW SIGNAL SQLSTATE '45000'
  SET MESSAGE_TEXT = 'audit_logs est en ajout seul';
```

> **Conséquence d'exploitation à assumer.** Les migrations tournent sous un utilisateur MySQL
> **distinct et plus privilégié** que l'utilisateur applicatif. C'est la contrepartie de la
> barrière 1, et c'est aussi une bonne pratique en soi. Elle est détaillée au § 25.4.

## 14.2 Écriture

Service unique `App\Support\Auditing\AuditLogger`, **appelé explicitement** dans les services
métier, plus un **observateur de filet de sécurité** sur les modèles listés par FR21.

L'appel explicite est le mécanisme principal, et c'est délibéré : un observateur capte le changement
technique mais ignore l'intention. FR20 exige l'action *et* le motif — « annulée pour double
saisie » n'est pas déductible d'un diff de colonnes. L'observateur ne sert qu'à garantir qu'une
écriture oubliée par un développeur laisse quand même une trace.

L'écriture d'audit **n'est jamais mise en file** et **n'est jamais différée** : NFR21 exige qu'elle
partage la transaction et que son échec fasse échouer l'opération.

## 14.3 Lecture — FR23 / D-04

`direction` **exclusivement**. `finance` n'y accède pas — c'est le registre qui la contrôle
(CONTRA-10). `super_admin` accède aux journaux techniques (fichiers), **pas** au journal d'audit
métier. Filtres par auteur, période, type d'objet, action (FR24). L'export CSV est réservé à
`direction` et **s'audite lui-même** (FR24, FR176).

## 14.4 Rétention

`audit_logs` **n'est jamais purgé** en MVP. Volumétrie estimée : ~200 écritures/jour → ~75 000
lignes/an, quelques dizaines de Mo sur 10 ans. Il n'y a aucune raison technique de purger, et une
raison métier impérative de ne pas le faire.

---
