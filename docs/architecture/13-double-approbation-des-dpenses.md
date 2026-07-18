# 13. Double approbation des dépenses

## 13.1 Règles à tenir

RM-09, RM-10, FR117 à FR122 : **deux comptes `direction` distincts, aucun seuil, aucune dérogation,
aucune délégation, et le demandeur n'est jamais approbateur** — y compris lorsqu'il est lui-même
`direction` (FR119). C'est le point le plus sensible du produit : c'est la défaillance qui a déjà
coûté à l'entreprise.

## 13.2 Modèle

```
expenses               id, requester_id, amount, category_id, state, reason, …
expense_approvals      id, expense_id, approver_id, decision(approve|reject),
                       comment, decided_at
                       UNIQUE (expense_id, approver_id)
```

L'état `approuvee` n'est **jamais saisi** : il est déduit de la présence de deux approbations
distinctes. Aucun écran, aucun service ne permet de le poser directement.

## 13.3 Application

Dans `ExpenseApprovalService::approve()`, sous transaction et `lockForUpdate()` sur la dépense :

| # | Contrôle | Règle |
|---|---|---|
| 1 | L'approbateur détient `depense.approuver` | PERM-05 |
| 2 | `approver_id !== requester_id` | **RM-10 / FR119 — refus absolu** |
| 3 | Aucune décision déjà prise par ce compte | Index unique, § 13.2 |
| 4 | La dépense est à l'état `demandee` | FR118 |
| 5 | Après écriture : si 2 approbations distinctes → `approuvee` | FR117 |
| 6 | Un refus met à `refusee` et **exige un motif** | FR122 |

Trois barrières superposées sur la règle 2 : Policy, règle de validation, et contrainte
`UNIQUE (expense_id, approver_id)` qui empêche mécaniquement le même compte de compter deux fois.

## 13.4 Approbation et paiement sont distincts — FR116

`state` (approbation) et `payment_state` (paiement) sont **deux colonnes séparées**. Une approbation
ne vaut jamais paiement. Le paiement (FR123, Étape 4) exige l'état `approuvee` et le rôle `finance`,
et `finance` **ne détient jamais `depense.approuver`** (§ 4.1 du PRD).

## 13.5 Alerte rouge — FR164

En niveau rouge, une dépense dont la catégorie n'est pas marquée « essentielle » **avertit sans
bloquer** : l'écran de décision affiche un bandeau explicite, la décision reste possible. Le blocage
en rouge ne porte que sur l'activation de nouveaux comptes (C9, RM-18 : le système bloque une
écriture, jamais une personne).

**FR165 :** les parts de 10 % et 30 % restent payables en rouge. Aucun garde-fou d'alerte ne
s'applique aux dépenses de versement de part — c'est une exception explicite à coder et à tester,
sinon l'implémentation naturelle les bloquera.

---
