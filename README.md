# PTR Staff

Application interne de coordination et de gestion, construite avec Laravel 13, Inertia.js 2,
Vue 3, Vite 8 et Tailwind CSS 4.

## Prérequis

- macOS avec MAMP et PHP 8.3.30 installé ;
- Node.js compatible avec Vite 8 et npm ;
- Composer fourni par MAMP ;
- SQLite pour le développement local.

## Installation locale complète

Depuis une copie vierge du dépôt :

```bash
export PATH="/Applications/MAMP/bin/php/php8.3.30/bin:$PATH"
alias composer='php /Applications/MAMP/bin/php/composer'

composer install
npm ci
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate
```

Dans un premier terminal, lancer le frontend :

```bash
npm run dev
```

Dans un second terminal, lancer Laravel :

```bash
php artisan serve
```

L’application est alors accessible sur `http://127.0.0.1:8000`. Le point de santé public se trouve
sur `http://127.0.0.1:8000/up`.

## Configuration locale

Le fichier `.env.example` conserve les horodatages applicatifs en UTC et définit
`APP_DISPLAY_TIMEZONE=Africa/Niamey` pour l’affichage. La locale est française. En story 1.1,
les sessions et le cache utilisent des fichiers, et la file est synchrone : Redis peut donc être
absent du poste local.

Les variables MySQL et Redis présentes dans `.env.example` sont des valeurs locales sans secret.
Le cache et les files passeront sur Redis en préproduction et production avec la story 1.5. Les
sessions passeront en base à partir de la story 2.4.

## Vérifications

```bash
php artisan test
vendor/bin/pint --dirty
vendor/bin/phpstan analyse
npm run build
```

La procédure ci-dessus est la procédure de référence à reproduire sur une installation propre.
