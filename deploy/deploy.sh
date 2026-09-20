#!/usr/bin/env bash
#
# Déploiement atomique par lien symbolique (story 11.1, AC 39 à 44).
#
# Le principe : la release en cours de construction n'est jamais servie. On la bâtit entièrement
# à côté, puis on repointe `current` d'un seul geste. À aucun moment un visiteur ne voit une
# application à moitié déployée — c'est ce qui permet de se passer de `php artisan down` (AC 41).
#
# Le retour arrière est le même geste dans l'autre sens : `rollback.sh` repointe `current` sur la
# release précédente, qui est toujours là, intacte.
#
# Usage :
#   deploy.sh <racine> <dépôt> <réf>
#   deploy.sh /srv/staffptr/production git@github.com:org/staffptr.git main
set -euo pipefail

APP_ROOT="${1:?Racine de déploiement attendue, par exemple /srv/staffptr/production}"
REPOSITORY="${2:?Dépôt Git attendu}"
GIT_REF="${3:-main}"

RELEASES_DIR="${APP_ROOT}/releases"
SHARED_DIR="${APP_ROOT}/shared"
CURRENT_LINK="${APP_ROOT}/current"
RELEASE_NAME="$(date -u +%Y%m%d%H%M%S)"
RELEASE_DIR="${RELEASES_DIR}/${RELEASE_NAME}"

# Nombre de releases conservées. Trois suffisent pour revenir en arrière deux fois ; au-delà, on
# occupe du disque sur un VPS partagé pour des versions que personne ne restaurera.
KEEP_RELEASES="${KEEP_RELEASES:-3}"

log() { printf '\n\033[1m▶ %s\033[0m\n' "$*"; }

log "Préparation de la release ${RELEASE_NAME}"
mkdir -p "${RELEASES_DIR}" "${SHARED_DIR}/storage" "${SHARED_DIR}/ops"

# ── 1. Code ───────────────────────────────────────────────────────────────────────────────────
#
# `git clone --branch` n'accepte qu'une branche ou une étiquette. Or le workflow passe
# `github.sha`, et c'est ce qu'il faut vouloir : déployer une branche, c'est déployer ce
# qu'elle contient au moment du clone, donc pas nécessairement le commit qui a été validé par
# l'intégration continue. Un `fetch` explicite accepte les deux formes et fige la release sur
# la référence demandée.
log "Récupération du code (${GIT_REF})"
mkdir -p "${RELEASE_DIR}"
git init -q "${RELEASE_DIR}"
git -C "${RELEASE_DIR}" remote add origin "${REPOSITORY}"

if ! git -C "${RELEASE_DIR}" fetch --depth 1 origin "${GIT_REF}"; then
  echo "✗ Référence « ${GIT_REF} » introuvable dans ${REPOSITORY}." >&2
  rm -rf "${RELEASE_DIR}"
  exit 1
fi

git -C "${RELEASE_DIR}" checkout -q --detach FETCH_HEAD
rm -rf "${RELEASE_DIR}/.git"

# ── 2. Dépendances PHP ────────────────────────────────────────────────────────────────────────
log "composer install --no-dev -o"
cd "${RELEASE_DIR}"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress

# ── 3. Assets ─────────────────────────────────────────────────────────────────────────────────
log "npm ci && npm run build"
npm ci --no-audit --no-fund
npm run build
# Les sources front ne servent à rien en production et pèsent lourd sur un disque partagé.
rm -rf node_modules

# ── 4. Liens vers shared ──────────────────────────────────────────────────────────────────────
#
# `storage` et `.env` sont **partagés entre les releases** : les pièces jointes et les secrets ne
# doivent pas disparaître au déploiement suivant. `shared/ops/` porte le registre de restauration,
# qui doit survivre à la rotation des releases (AC 16).
log "Liaison des répertoires persistants"
rm -rf "${RELEASE_DIR}/storage"
ln -s "${SHARED_DIR}/storage" "${RELEASE_DIR}/storage"
ln -sf "${SHARED_DIR}/.env" "${RELEASE_DIR}/.env"

# ── 5. Migrations ─────────────────────────────────────────────────────────────────────────────
#
# Les migrations tournent avec l'utilisateur **privilégié**, distinct de l'utilisateur applicatif
# qui n'a pas le droit de modifier le schéma. Les identifiants ne sont injectés que le temps de
# cette commande, jamais écrits dans un fichier de la release.
#
# La connexion est nommée explicitement : sans `--database`, Laravel migrerait sur la connexion
# par défaut, celle du compte applicatif, qui n'a ni DDL ni `GRANT OPTION`. Les migrations qui
# accordent `UPDATE` table par table — la matrice de `docs/ops/database-users.md` — échoueraient
# alors, et le déploiement serait annulé juste après.
MIGRATION_CONNECTION="${MIGRATION_CONNECTION:-mysql_migration}"

# Vérification préalable : mieux vaut un message lisible ici qu'une trace PDO au milieu des
# migrations, une fois la release à moitié appliquée.
if ! php artisan db:show --database="${MIGRATION_CONNECTION}" >/dev/null 2>&1; then
  echo "La connexion « ${MIGRATION_CONNECTION} » est injoignable." >&2
  echo "Renseignez DB_MIGRATION_USERNAME et DB_MIGRATION_PASSWORD dans le .env partagé," >&2
  echo "avec un compte détenant ALL PRIVILEGES ... WITH GRANT OPTION sur ce schéma." >&2
  exit 1
fi

log "migrate --force (connexion ${MIGRATION_CONNECTION})"
php artisan migrate --database="${MIGRATION_CONNECTION}" --force --no-interaction

# ── 6. Caches ─────────────────────────────────────────────────────────────────────────────────
#
# Après la liaison de `shared`, jamais avant : un cache de configuration construit sans le `.env`
# définitif figerait les mauvaises valeurs pour toute la durée de vie de la release.
log "Mise en cache de la configuration"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ── 7. Bascule atomique ───────────────────────────────────────────────────────────────────────
#
# `ln -sfn` sur un lien temporaire puis `mv` : le remplacement est atomique au niveau du système
# de fichiers. Un `rm` suivi d'un `ln` laisserait une fenêtre — courte, mais réelle — pendant
# laquelle `current` n'existe pas et le serveur renvoie une erreur.
log "Bascule de current vers ${RELEASE_NAME}"
PREVIOUS_RELEASE="$(readlink -f "${CURRENT_LINK}" 2>/dev/null || true)"
ln -sfn "${RELEASE_DIR}" "${CURRENT_LINK}.tmp"
mv -Tf "${CURRENT_LINK}.tmp" "${CURRENT_LINK}"

# ── 8. Rechargement ───────────────────────────────────────────────────────────────────────────
log "Rechargement PHP-FPM et redémarrage des workers"
sudo systemctl reload "php${PHP_VERSION:-8.3}-fpm"
# AC 29 — les workers exécutent l'ancien code tant qu'ils n'ont pas redémarré.
php artisan queue:restart

# ── 9. Porte post-déploiement ─────────────────────────────────────────────────────────────────
#
# AC 44 — `/up` et les invariants sont vérifiés. En cas d'échec, retour arrière **automatique** :
# un déploiement qui casse la production ne doit pas attendre qu'un humain s'en aperçoive.
log "Vérification post-déploiement"
POST_DEPLOY_OK=1

if ! curl -fsS --max-time 15 "${HEALTH_URL:-http://127.0.0.1/up}" >/dev/null; then
  echo "✗ /up ne répond pas correctement."
  POST_DEPLOY_OK=0
fi

if ! php artisan ptr:check-invariants; then
  echo "✗ Les invariants sont en écart."
  POST_DEPLOY_OK=0
fi

if [ "${POST_DEPLOY_OK}" -eq 0 ]; then
  echo "✗ Porte post-déploiement en échec — retour arrière automatique."

  if [ -n "${PREVIOUS_RELEASE}" ] && [ -d "${PREVIOUS_RELEASE}" ]; then
    "$(dirname "$0")/rollback.sh" "${APP_ROOT}" "${PREVIOUS_RELEASE}"
  else
    echo "✗ Aucune release précédente : retour arrière impossible, intervention humaine requise."
  fi

  exit 1
fi

# ── 10. Rotation ──────────────────────────────────────────────────────────────────────────────
#
# À ce stade le déploiement est fait et vérifié : `current` sert la nouvelle release, la porte
# post-déploiement est passée. Le ménage qui suit n'est que du confort de disque, et son échec
# — un fichier qu'une intervention manuelle a laissé à un autre propriétaire, par exemple — ne
# doit ni annuler ce qui fonctionne, ni empêcher la mise en production qui en dépend. On le
# signale donc au lieu d'échouer, sous `set -e` qui ferait autrement tomber tout le script.
log "Rotation des releases (${KEEP_RELEASES} conservées)"
cd "${RELEASES_DIR}"

if ! ls -1dt */ 2>/dev/null | tail -n "+$((KEEP_RELEASES + 1))" | xargs -r rm -rf; then
  echo "⚠ Rotation incomplète : d'anciennes releases n'ont pas pu être supprimées." >&2
  echo "  Le déploiement reste valide. Vérifiez les propriétaires dans ${RELEASES_DIR}." >&2
fi

log "Déploiement ${RELEASE_NAME} terminé."
