# staffptr

Application Laravel 13 (PHP 8.3) pilotée avec la méthode BMAD.

## Environnement local

PHP et Composer proviennent de MAMP — ils ne sont pas dans le `PATH` par défaut :

```bash
export PATH="/Applications/MAMP/bin/php/php8.3.30/bin:$PATH"
alias composer='php /Applications/MAMP/bin/php/composer'
```

## Commandes

```bash
php artisan serve                 # serveur de dev
npm run dev                       # Vite
php artisan test --filter=<name>  # tests ciblés
php artisan test                  # suite complète
vendor/bin/pint --dirty           # formatage (obligatoire avant de finaliser)
```

Si un changement front n'apparaît pas dans l'UI, demander à lancer `npm run dev` ou `npm run build`.

## Conventions

Les standards font foi dans `docs/architecture/coding-standards.md`, la stack dans
`docs/architecture/tech-stack.md`, l'arborescence dans `docs/architecture/source-tree.md`.
Lire ces trois fichiers avant d'écrire du code.

En résumé : Eloquent plutôt que `DB::`, Form Requests pour la validation, types de retour
explicites partout, `php artisan make:*` pour créer les fichiers, un test par changement.

## Méthode BMAD

Agents disponibles en slash commands : `/analyst`, `/pm`, `/architect`, `/po`, `/sm`, `/dev`,
`/qa`, `/ux-expert`, `/bmad-master`, `/bmad-orchestrator`.

Flux classique : `/analyst` (brief) → `/pm` (PRD dans `docs/prd.md`) → `/architect`
(`docs/architecture.md`) → `/po` (shard des docs) → boucle `/sm` (story) → `/dev` (implémentation)
→ `/qa` (revue et gate).

Les mêmes agents sont lisibles par Codex via `AGENTS.md`. Après mise à jour de BMAD :
`npx bmad-method install -f -i claude-code -i codex`.

## Documentation

Ne créer de fichier de documentation que si l'utilisateur le demande explicitement — sauf les
artefacts BMAD dans `docs/`, qui font partie du flux de travail.
