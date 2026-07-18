# Coding Standards

Règles que tout agent BMAD (`dev`, `qa`) doit respecter dans ce dépôt.

## PHP

- Toujours des accolades pour les structures de contrôle, même sur une ligne.
- Promotion de propriétés dans `__construct()` (PHP 8) ; pas de constructeur vide sans paramètre.
- Types de retour explicites sur toutes les méthodes et fonctions, type-hints sur les paramètres.
- PHPDoc plutôt que commentaires inline ; documenter la forme des tableaux (`array{...}`).
- Clés d'enum en `TitleCase` (`Monthly`, `PendingReview`).
- Noms descriptifs : `isRegisteredForDiscounts()`, pas `discount()`.

## Laravel

- Créer les fichiers avec `php artisan make:*` et `--no-interaction`.
- Eloquent d'abord : relations typées, pas de `DB::`, eager loading pour éviter les N+1.
- Validation dans des Form Requests, jamais inline dans un contrôleur.
- Pages rendues par Inertia. **Pas d'API publique**, pas de `routes/api.php`. Les API Resources
  servent à former les props Inertia.
- Trois exceptions répondent en JSON, toutes internes et authentifiées par session : brouillons et
  autocomplétion, téléversement de pièce jointe, et `/up`. Les deux premières sont **versionnées
  `/internal/v1/…`** — un client mobile de phase 2 ne doit pas forcer la réécriture des chemins
  (architecture § 10.1).
- Logique métier transactionnelle dans `app/Services/{Module}/`, jamais dans le contrôleur.
- Opérations longues : jobs en file (`ShouldQueue`).
- `env()` uniquement dans `config/` ; ailleurs, `config('...')`.
- Liens générés via `route()` et des routes nommées.
- Un nouveau modèle vient avec sa factory et son seeder.

## Front

- Tailwind v4 : utilitaires non dépréciés (`shrink-*`, `bg-black/50`, …), config via `@theme`.
- Espacement des listes avec `gap-*`, pas des marges.

## Tests

- Chaque changement est couvert par un test nouveau ou mis à jour, puis exécuté.
- Tests PHPUnit (pas Pest) : `php artisan make:test --phpunit {Name}`.
- Filtrer pendant le développement : `php artisan test --filter=nomDuTest`.
- Couvrir chemins nominaux, chemins d'erreur et cas limites.
- Utiliser les factories et leurs states plutôt qu'un setup manuel.

## Non négociable dans ce produit

Ces six règles priment sur toute considération de confort. Le détail est dans
`docs/prd/socle-transverse.md` (SOC-01 à SOC-11), chargé lui aussi à chaque story.

- **Autorisation serveur sur chaque requête**, indépendamment du menu. Toute route protégée est
  déclarée dans `config/authorization-matrix.php`, sinon la CI échoue.
- **Audit dans la même transaction** que l'opération métier ; son échec annule l'opération.
- **Aucune suppression physique** : correction versionnée, annulation motivée ou contre-écriture.
- **Montants en entiers XOF** (`BIGINT UNSIGNED`), jamais de flottant ni de `DECIMAL`.
- **Horodatages en UTC**, affichés en `Africa/Niamey`.
- **Une migration déployée n'est jamais modifiée** ; toute évolution est une nouvelle migration.

## Avant de finaliser

```bash
vendor/bin/pint --dirty
php artisan test --filter=<ce qui est touché>
vendor/bin/phpstan analyse          # Larastan niveau 6
```
