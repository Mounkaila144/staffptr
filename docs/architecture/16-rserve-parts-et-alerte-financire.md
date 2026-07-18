# 16. Réserve, parts et alerte financière

## 16.1 Principe de calcul

**Rien n'est stocké en solde ; tout est un livre auxiliaire.** Le solde d'un compte (FR100), le
montant de la réserve (FR145) et le niveau d'alerte (FR160) sont **calculés**, jamais saisis. Un
solde stocké diverge toujours, et une divergence sur ces montants est exactement ce que le produit
existe pour empêcher.

## 16.2 Parts de contrat — FR128 à FR136

`ShareCalculator`, service pur, sans état, **entièrement couvert de tests unitaires** :

| Cas | Répartition |
|---|---|
| Apporteur + exécution | 10 % apporteur / 60 % PTR Niger / 30 % exécutants (RM-12) |
| Apporteur, sans exécution | 10 % / 90 % PTR Niger (FR129) |
| Sans apporteur | 100 % PTR Niger (FR128) |
| Plusieurs exécutants | 30 % en parts **strictement égales** (FR130) |

Déclenchement **à l'encaissement réel** (RM-13, FR132), au prorata de l'encaissé sur le total attendu
(FR131). Un contrat facturé non payé ne génère **aucune** part.

**Arithmétique entière.** Les montants sont des entiers XOF sans décimales (RM-02, NFR22). Un
partage en trois d'un montant non divisible produit un reste. Convention retenue : **division
entière, et le reste est attribué au bénéficiaire de rang 1** selon un ordre stable et affiché.
La somme des parts est ainsi toujours exactement égale à la base — un test le vérifie sur des
montants aléatoires. FR135 impose d'afficher la méthode de calcul : le reste attribué en fait partie.

> **CONTRA-01 non tranché.** Le bénéfice servant de base est le **bénéfice prévisionnel** du contrat
> (FR104), avec régularisation à la clôture. Tant que la direction n'a pas arbitré, `ShareCalculator`
> prend la base en paramètre explicite et ne la déduit pas lui-même. Le basculement vers
> « versement uniquement à la clôture » resterait alors un changement de service, pas de schéma.

## 16.3 Réserve — FR142 à FR147

- Objectif = mois paramétrés × Σ charges fixes **actives** (FR142, FR139).
- Alimentation = 20 % du bénéfice de l'encaissement, **prélevés sur la part de 60 % PTR Niger**,
  sans jamais toucher les 10 % et 30 % (RM-11, FR143).
- Arrêt et reprise **automatiques** au franchissement de l'objectif (FR144) : évalués à chaque
  encaissement, jamais par tâche planifiée — un prélèvement doit être décidé au moment de l'écriture
  qui le motive.
- Livre `reserve_movements` : `allocation` (+) et `usage` (−). L'usage exige motif, double
  approbation `direction` et plan de reconstitution enregistré (FR146).
- FR147 — l'impact chiffré de l'ajout d'une charge fixe sur l'objectif est calculé et affiché
  **avant** confirmation : c'est un appel de prévisualisation, pas un message générique.

## 16.4 Alerte — FR161 à FR165

| Niveau | Condition | Effets |
|---|---|---|
| **Vert** | Encaissements du mois ≥ assiette | Aucun |
| **Orange** | 1 mois sous l'assiette | Plan correctif exigé sous 48 h, notification `direction` répétée jusqu'à existence du plan |
| **Rouge** | 2 mois consécutifs sous l'assiette | **Bloque** l'activation de tout nouveau compte employé/stagiaire ; **avertit sans bloquer** sur dépense non essentielle |

L'assiette est **toujours** la somme des charges fixes actives déclarées au paramétrage, jamais une
liste codée en dur (FR161). Le niveau courant est calculé à la demande et mis en cache ; il est
**figé** à la validation du rapport mensuel (FR160) dans `monthly_reports.alert_level`.

**Rappel RM-18 / CONTRA-07 :** l'alerte rouge ne bloque **aucune personne** et **aucun versement de
part**. Elle bloque une activation de compte et affiche un avertissement. Rien d'autre.

---
