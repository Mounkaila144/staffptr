# REST API Spec

**Il n'y a pas d'API publique.** L'application utilise Inertia : les contrôleurs rendent des pages
Vue, le routage et l'autorisation restent serveur. Pas de `routes/api.php`, pas de versioning de
routes, pas de jeton d'API, pas de client externe.

Conventions de contrôleur et contrat Inertia : `docs/architecture.md` § 10, shard
`10-api-contrleurs-et-conventions-de-validation.md`.
