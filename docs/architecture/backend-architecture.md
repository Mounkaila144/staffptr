# Backend Architecture

> Monolithe modulaire Laravel 13, structure « slim ». Source : `docs/architecture.md`.

| Sujet | Shard |
|---|---|
| Style architectural, ce que l'architecture ne fait pas | `3-vue-densemble.md` |
| Cinq modules, règle de couplage | `5-structure-des-modules.md` → voir `source-tree.md` |
| Conventions de contrôleur, contrat Inertia, validation, réponses d'erreur | `10-api-contrleurs-et-conventions-de-validation.md` |
| RBAC, quatre niveaux de contrôle, règles structurelles | `8-rbac-et-permissions-serveur.md` |
| Authentification téléphone, sessions, blocage | `7-…`, `9-sessions-scurit-des-comptes-et-notifications.md` |
| Transactions financières atomiques, concurrence, idempotence | `12-transactions-financires-atomiques.md` |
| Double approbation des dépenses | `13-double-approbation-des-dpenses.md` |
| Journal d'audit non modifiable | `14-journal-daudit-non-modifiable.md` |
| Réserve, parts, alerte financière | `16-rserve-parts-et-alerte-financire.md` |
| Pièces jointes privées | `11-pices-jointes-prives.md` |

## À retenir avant d'écrire du code serveur

- **Validation exclusivement par Form Request.** Jamais inline dans un contrôleur.
- **Logique métier transactionnelle dans `app/Services/{Module}/`**, pas dans le contrôleur.
- **Une policy par modèle protégé.** L'autorisation est vérifiée serveur, sur la requête,
  indépendamment de l'affichage du menu.
- **L'écriture d'audit se fait dans la transaction de l'opération métier.** Son échec annule
  l'opération (NFR21).
