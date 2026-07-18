# Data Models

> Source de vérité : `docs/architecture.md` § 6 — shard **`6-modle-de-donnes.md`**.

Règles transverses à connaître avant d'écrire un modèle :

- **Montants** : `BIGINT UNSIGNED`, entiers XOF, cast `integer`. Aucun `DECIMAL`, aucun flottant (NFR22).

## Convention de nommage monétaire — opposable

Établie par la story 1.2, appliquée par un test de convention qui scanne
`database/migrations/`.

| Règle | Détail |
|---|---|
| **Nommage** | Toute colonne portant un montant se termine par **`_amount`** — `total_amount`, `expected_amount`, `paid_amount`. Aucune exception. |
| **Type** | Toute colonne `*_amount` est **`BIGINT UNSIGNED`**. ⛔ Un test échoue si une migration déclare une colonne `*_amount` en `decimal`, `float`, `double` ou `unsignedDecimal`. |
| **Affichage** | `Support\Money::format()` est réservé à l'**affichage**. Tout export, calcul, comparaison ou écriture en base manipule l'**entier brut**. |

La troisième règle n'est pas cosmétique : l'export CSV de la story 10.3 doit produire des entiers.
Un tableur français ne parse pas « 1 250 000 FCFA » comme un nombre, et le séparateur retenu est une
espace insécable fine (U+202F). Un montant formaté qui franchit la frontière d'un export ou d'une
persistance est un défaut.

### L'arithmétique des parts appartient à `Support\Money` — pas aux stories qui l'utilisent

`Money` livré en 1.2 porte la valeur et le formatage, **pas encore l'arithmétique**. Les stories
**8.3** (répartition 10 / 60 / 30) et **8.7** (parts au prorata des encaissements) en ont besoin, et
⛔ la **règle bloquante 14** exige que « la somme des parts soit exactement égale à la base, le reste
entier attribué de façon déterministe ».

Cette arithmétique — division entière, distribution du reste, prorata — **doit être ajoutée à
`Support\Money` et testée là**, jamais réimplémentée dans un service financier. Trois stories
distinctes (8.3, 8.7, 8.11) répartissent des entiers XOF ; trois implémentations inline du reste
produiraient trois arrondis différents, et la règle 14 échouerait sur l'une d'elles sans qu'on sache
laquelle.

Contrainte connexe : `Money` refuse les montants négatifs, ce qui est cohérent avec `BIGINT UNSIGNED`.
Une contre-écriture (8.5, 8.6) se modélise donc par un **montant positif de sens opposé**, jamais par
un montant négatif.
- **Horodatages** : stockés en **UTC**, affichés en `Africa/Niamey` (DEC-01).
- **Aucune suppression physique** : pas de `SoftDeletes` comme substitut — correction versionnée,
  annulation motivée ou contre-écriture (P2, RM-17).
- **Séparation `people` / `users`** : la fiche personne survit au compte (A-06, CONTRA-02).
- Aucune colonne de locataire — l'application n'est pas multi-entreprise (NFR28).

Sections détaillées :

| Sujet | Shard |
|---|---|
| Règles transverses, noyau identité, noyau financier, journal d'audit | `6-modle-de-donnes.md` |
| Ordre de dépendance des migrations, seeders de référence | `20-migrations-et-donnes-initiales.md` |
| Immuabilité, historiques, annulations, clôture mensuelle | `15-immuabilit-historiques-et-annulations.md` |
| Journal d'audit et ses trois barrières | `14-journal-daudit-non-modifiable.md` |
