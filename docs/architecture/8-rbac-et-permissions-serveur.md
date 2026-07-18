# 8. RBAC et permissions serveur

## 8.1 Modèle

`spatie/laravel-permission` (DEC-03) fournit exactement la sémantique de FR11 : rôles multiples par
utilisateur **et** permissions unitaires directes, les permissions effectives étant l'union des deux.
Il satisfait PERM-07 sans refonte — créer plus tard un rôle `auditeur` en lecture seule est une
insertion de données, pas une modification de modèle.

Six rôles applicatifs (§ 4.1 du PRD) et un jeu de permissions nommées par domaine et verbe :
`depense.approuver`, `audit.consulter`, `finance.ecriture.creer`, `objectif.valider`, …

## 8.2 Les quatre niveaux de contrôle

L'autorisation est vérifiée **côté serveur sur chaque requête** (P4, PERM-01, NFR14). Quatre
niveaux, du plus grossier au plus fin :

| Niveau | Mécanisme | Répond à |
|---|---|---|
| 1 — Route | Middleware `auth`, `EnsureAccountActive`, `EnsurePasswordChanged` | FR7, FR5 |
| 2 — Permission | Middleware `permission:depense.approuver` sur le groupe de routes | PERM-01 |
| 3 — Objet | **Policy Laravel** — `$this->authorize('approve', $expense)` | PERM-02, NFR18 |
| 4 — Portée de données | **Global scopes** et scopes explicites de visibilité | NFR18, NFR19 |

Le niveau 4 est celui que l'on oublie et celui qui fuit. La matrice § 4.3 du PRD distingue
« Tous / Son équipe / Les siens » : cette portée est implémentée par des scopes de requête nommés
(`visibleTo(User $user)`) appliqués **dans le contrôleur d'index et dans l'export**, jamais laissés
au filtrage côté client.

## 8.3 Règles structurelles

- **PERM-03 — `super_admin` n'a aucune permission métier.** Conséquence directe et impérative :
  **il est interdit d'implémenter un `Gate::before()` accordant tout au super administrateur.**
  C'est l'idiome Laravel réflexe, et il violerait le PRD. Un test dédié vérifie qu'un `super_admin`
  reçoit bien `403` sur l'approbation d'une dépense, la validation d'un objectif et la validation
  financière.
- **PERM-05 — `depense.approuver` n'appartient qu'aux deux comptes `direction`.** Une commande de
  vérification d'intégrité (`ptr:check-invariants`, § 24.3) signale tout écart.
- **PERM-02 — refus, jamais contenu partiel.** Le gestionnaire d'exceptions renvoie `403` avec une
  page Inertia d'erreur en français. **Aucune redirection silencieuse vers l'accueil** : elle
  masquerait un défaut d'autorisation en le faisant passer pour de la navigation.
- **PERM-04 — toute attribution de rôle ou de permission est auditée** avec ancienne et nouvelle
  valeur, via le service `RoleAssignmentService` seul habilité à écrire ces tables.
- **PERM-06 — un export applique les mêmes filtres que son écran d'origine.** Garanti
  structurellement : l'export **réutilise le même scope de visibilité** que l'index, il ne
  reconstruit jamais sa propre requête. Tout export est audité (FR176).

## 8.4 Campagne de tests d'accès — NFR14

Exigence de recette de chaque étape, automatisée dès l'Étape 1. Voir § 23.3.

---
