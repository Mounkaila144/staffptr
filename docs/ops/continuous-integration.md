# Intégration continue

La chaîne `Pull Request Quality` s'exécute sur chaque pull request visant `main`. Elle installe les
dépendances depuis `composer.lock` et `package-lock.json` et ne réutilise jamais un résultat de test
mis en cache.

## Contrôles obligatoires

La protection de `main` dans `Mounkaila144/staffptr` impose une pull request et les contrôles aux
noms stables suivants :

- `Pint`
- `Larastan`
- `PHPUnit (MySQL 8)`
- `Frontend Build & Budget`
- `Playwright`
- `CI Duration`

Un contrôle rouge interdit la fusion. Aucun administrateur ne doit contourner ces contrôles.

## MySQL 8

Le job PHPUnit utilise le service Docker `mysql:8.0` avec des identifiants éphémères propres à la
CI. Il affiche le moteur avant les migrations. `CiDatabaseEngineTest` échoue si le pilote n'est pas
MySQL ou si le serveur n'est pas en version 8.0. SQLite reste le profil local rapide ; il ne constitue
pas une preuve des contraintes propres à MySQL.

## Budget du bundle

La commande `npm run check:bundle` lit le manifeste Vite et suit les imports de l'entrée applicative
et de la page `Platform/Demo`. Chaque fichier transféré est compté une seule fois puis compressé avec
Brotli qualité 11. La somme doit rester inférieure ou égale à 300 Ko. Le script accepte plusieurs
paramètres `--entry` afin d'ajouter les futures pages du parcours quotidien sans modifier le workflow.

Le poids mesuré, la limite, les fichiers comptabilisés et le résultat sont écrits dans le résumé du
job GitHub Actions.

## Playwright

Le premier parcours E2E démarre Laravel, ouvre la page de démonstration dans Chromium et vérifie le
rendu utile, les erreurs navigateur et l'absence de requête tierce. Les traces et captures ne sont
conservées qu'en cas d'échec. Le helper `tests/e2e/support/network.js` prépare l'émulation future à
400 kbit/s et 400 ms de latence.

## Preuve de protection et de durée

| Élément | Valeur |
|---|---|
| Remote | `https://github.com/Mounkaila144/staffptr.git` |
| Branche protégée | `main` — Pull request obligatoire, contrôles stricts et administrateurs inclus |
| Pull request de preuve | [PR #1](https://github.com/Mounkaila144/staffptr/pull/1) |
| Exécution GitHub Actions verte | [29674103335](https://github.com/Mounkaila144/staffptr/actions/runs/29674103335) |
| Exécution rouge de contrôle | [29674070332](https://github.com/Mounkaila144/staffptr/actions/runs/29674070332) — `Frontend Build & Budget` en échec |
| Effet de la protection | PR passée de `CLEAN` à `BLOCKED` pendant l'exécution rouge, puis revenue à `CLEAN` après correction |
| Durée totale | 60 s — seuil strict `< 600 s` |
| Date de mesure | 2026-07-19 |

Durées des contrôles sur l'exécution verte :

| Contrôle | Durée | Résultat |
|---|---:|---|
| `Pint` | 17 s | Succès |
| `Larastan` | 16 s | Succès |
| `PHPUnit (MySQL 8)` | 51 s | Succès — MySQL 8.0.46, 38 tests / 217 assertions |
| `Frontend Build & Budget` | 14 s | Succès — 80,55 Ko Brotli / 300 Ko |
| `Playwright` | 45 s | Succès |
| `CI Duration` | 4 s | Succès |

La protection exige les six contrôles ci-dessus en mode strict et s'applique aussi aux
administrateurs. Les conversations doivent être résolues ; les force-pushs et suppressions de
`main` sont interdits. La sonde rouge a été retirée après vérification sans modifier ni contourner
ces règles.

Le job `CI Duration` interroge l'API GitHub en fin de chaîne, consigne la durée totale et les durées
des contrôles terminés dans son résumé, puis échoue dès que la durée atteint 600 secondes.
