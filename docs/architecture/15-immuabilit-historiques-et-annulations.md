# 15. Immuabilité, historiques et annulations

## 15.1 Règle — P2 / RM-17 / NFR20

Aucune donnée financière ni objet validé ne se supprime. **`SoftDeletes` n'est pas utilisé sur les
tables métier** : un `deleted_at` est une suppression déguisée qui fait disparaître la ligne de
toutes les requêtes par défaut. Ce n'est pas ce que demande le PRD.

## 15.2 Les trois opérations autorisées

| Opération | Mécanisme | Trace |
|---|---|---|
| **Correction** | Nouvelle version liée à la précédente | Motif obligatoire + audit |
| **Annulation** | Contre-écriture liée à l'originale | Motif obligatoire + audit |
| **Clôture** | Verrou de période | Réouverture auditée (FR159) |

Motif de versionnement, appliqué aux encaissements (FR111), objectifs validés (CA-06), rapports
quotidiens et rapprochements (FR152) :

```
payments   id, …, version, supersedes_id, superseded_by_id,
               cancelled_at, cancellation_reason, cancelled_by
```

La ligne d'origine **reste en base, inchangée**. Une vue applicative « écritures courantes » filtre
`superseded_by_id IS NULL AND cancelled_at IS NULL`. **L'historique complet reste toujours
consultable** — c'est le sens de « sans suppression silencieuse ».

## 15.3 Application

- Trait `Immutable` sur les modèles d'écriture validée : `update()` et `delete()` lèvent.
- **Aucune route `DELETE`** n'existe sur les ressources financières et les objets validés. Vérifié
  par un test qui énumère la table de routage — pas par relecture humaine.
- Retrait du privilège `DELETE` à l'utilisateur applicatif sur les tables d'écritures financières,
  comme pour `audit_logs`.
- Le numéro de reçu (FR110) est attribué par séquence dédiée et **jamais réutilisé, même après
  annulation** : une contre-écriture consomme son propre numéro.

## 15.4 Clôture mensuelle — FR158 / FR159

`month_closures` : `year_month` (unique), `closed_at`, `closed_by`, `alert_level_frozen`,
`reopened_at`, `reopened_by`, `reopen_reason`.

`MonthGuard::assertOpen(date)` est appelé **dans la transaction** de toute écriture financière,
après acquisition des verrous. Une écriture postérieure à une réouverture porte
`recorded_after_reopen = true` (FR159) : elle reste identifiable dans le rapport ultérieur.

---
