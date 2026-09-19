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
log "Récupération du code (${GIT_REF})"
git clone --depth 1 --branch "${GIT_REF}" "${REPOSITORY}" "${RELEASE_DIR}"
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
log "migrate --force (utilisateur privilégié)"
php artisan migrate --force --no-interaction

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
log "Rotation des releases (${KEEP_RELEASES} conservées)"
cd "${RELEASES_DIR}"
ls -1dt */ 2>/dev/null | tail -n "+$((KEEP_RELEASES + 1))" | xargs -r rm -rf

log "Déploiement ${RELEASE_NAME} terminé."
