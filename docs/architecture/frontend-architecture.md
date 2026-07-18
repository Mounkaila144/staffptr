# Frontend Architecture

> Vue 3 + Inertia 2 rendus par Laravel. Pas de SPA séparée, pas de SSR, pas de PWA.
> Sources : `docs/architecture.md` § 3, § 18, § 19 et `docs/front-end-spec.md`.

| Sujet | Où |
|---|---|
| Arborescence `resources/js/` | `source-tree.md` |
| Contrat Inertia (props partagées, permissions) | shard `10-api-contrleurs-et-conventions-de-validation.md` |
| Performance sur connexion faible, budget de poids | shard `18-performance-sur-connexion-faible.md` |
| Cache et brouillons locaux (`useDraft`) | shard `19-cache-et-brouillons.md` |
| Navigation par rôle, parcours, wireframes | `docs/front-end-spec.md` § 3 à § 5 |
| États vides, chargement, hors connexion, erreur | `docs/front-end-spec.md` § 5.6 + `docs/prd/socle-transverse.md` |

## Contraintes qui pèsent sur chaque page

- **Aucune bibliothèque de composants externe**, aucune ressource tierce à l'exécution (NFR3).
  Le système de design est propre et minimal.
- **≤ 300 Ko** au premier chargement, **≤ 80 Ko** ensuite, hors pièces jointes (NFR2) — vérifié en CI.
- **Premier rendu utile < 3 s** en 3G dégradée (400 kbit/s / 400 ms) (NFR1).
- **320 px sans défilement horizontal**, cibles tactiles **≥ 44 × 44 px** (NFR7, NFR8).
- **Aucune information portée par la couleur seule** (NFR31). WCAG 2.1 AA.
- Le contrôle d'accès affiché n'est jamais la sécurité : un bloc non autorisé est **absent**, et le
  serveur refuse de toute façon.
