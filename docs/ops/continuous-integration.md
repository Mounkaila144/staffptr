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
| Branche protégée | `main` — Pull request obligatoire |
| Pull request de preuve | À renseigner après la première exécution |
| Exécution GitHub Actions | À renseigner après la première exécution |
| Durée totale | À mesurer — seuil strict `< 600 s` |
| Date de mesure | À renseigner |

Le job `CI Duration` interroge l'API GitHub en fin de chaîne, consigne la durée totale et les durées
des contrôles terminés dans son résumé, puis échoue dès que la durée atteint 600 secondes.
