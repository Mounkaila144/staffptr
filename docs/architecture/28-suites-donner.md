# 28. Suites à donner

1. **Trancher DEC-01 à DEC-11**, en priorité DEC-06 (sauvegardes hors site) et DEC-09 (Q6) qui
   dépendent de vous seul.
2. **Faire arbitrer CONTRA-01, 03, 04, 05 et 07** avant l'écriture du modèle financier de l'Étape 4.
3. **Lancer l'agent PO** pour sharder ce document vers `docs/architecture/` et vérifier la cohérence
   PRD ↔ architecture. Les fichiers `coding-standards.md`, `tech-stack.md` et `source-tree.md`
   existants seront **mis à jour** par le shard — `tech-stack.md` porte encore une section « À
   décider » que ce document rend caduque.
4. **Démarrer la boucle `/sm` → `/dev` → `/qa`** sur l'Epic 1, Story 1.1.

## Ordre d'implémentation imposé par l'architecture

Cet ordre n'est pas une préférence : chaque élément est un prérequis technique du suivant.

```
1. Fondation + /up                         Story 1.1
2. audit_logs + déclencheurs + AuditLogger Story 1.2  ← avant toute écriture sensible
3. people / users + normalisation +227     Story 1.4
4. Rôles, permissions, policies            Story 1.3
5. Connexion, changement imposé, sessions  Story 1.5
6. Campagne d'autorisation automatisée     NFR14  ← dès la première ressource protégée
7. Paramètres + pièces jointes privées     Stories 1.6+
8. Dépenses + double approbation           Stories 1.11–1.13
```

Le point 2 avant tout le reste est la traduction directe de l'exigence du PRD : le journal d'audit
doit être opérationnel **avant** la première écriture sensible, faute de quoi les premières
opérations de l'application seront les seules à ne pas être traçables.

---
