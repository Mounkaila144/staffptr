# 28. Suites à donner

1. **Trancher DEC-01 à DEC-11**, en priorité DEC-06 (sauvegardes hors site) et DEC-09 (Q6) qui
   dépendent de vous seul.
2. **Faire arbitrer CONTRA-01, 03, 04, 05 et 07** avant l'écriture du modèle financier de l'Étape 4.
3. **Lancer l'agent PO** pour sharder ce document vers `docs/architecture/` et vérifier la cohérence
   PRD ↔ architecture. Les fichiers `coding-standards.md`, `tech-stack.md` et `source-tree.md`
   existants seront **mis à jour** par le shard — `tech-stack.md` porte encore une section « À
   décider » que ce document rend caduque.
4. **Démarrer la boucle `/sm` → `/dev` → `/qa`** sur l’Epic 1, Story 1.1 du plan d’exécution (`docs/prd/epic-1-fondation-technique.md`).

## Ordre d'implémentation imposé par l'architecture

Cet ordre n'est pas une préférence : chaque élément est un prérequis technique du suivant.

> **Numérotation.** Les identifiants ci-dessous sont ceux du **plan d'exécution**
> (`docs/epics-stories.md`, 11 epics), qui font foi pour la boucle `/sm` → `/dev`. Ils ne
> correspondent pas aux stories du § 10 du PRD, qui suit un découpage en 4 epics. La
> correspondance complète est dans `docs/prd/tracabilite.md`.

```
1. Fondation + /up                         Story 1.1        [PRD 1.1]
2. Monnaie XOF, fuseau, téléphone +227     Story 1.2        [PRD 1.1]
3. audit_logs + déclencheurs + AuditLogger Story 1.4        [PRD 1.2]
                                           ← avant toute écriture sensible
4. people / users + unicité conditionnelle Story 2.1        [PRD 1.4]
5. Rôles, permissions, policies            Story 2.2        [PRD 1.3]
6. Premier administrateur + seeders        Story 2.3        [nouveau]
7. Connexion, changement imposé, sessions  Stories 2.4-2.6  [PRD 1.5]
8. Campagne d'autorisation automatisée     Story 2.9  NFR14
                                           ← dès la première ressource protégée
9. Paramètres + pièces jointes privées     Stories 3.4, 3.5 [PRD 1.7, 2.7]
10. Dépenses + double approbation          Stories 4.4-4.6  [PRD 1.11-1.13]
```

Le point 2 avant tout le reste est la traduction directe de l'exigence du PRD : le journal d'audit
doit être opérationnel **avant** la première écriture sensible, faute de quoi les premières
opérations de l'application seront les seules à ne pas être traçables.

---
