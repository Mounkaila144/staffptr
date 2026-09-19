#!/usr/bin/env bash
#
# Retour arrière par repointage du lien symbolique (story 11.1, AC 39, AC 44, AC 58).
#
# Le retour arrière est **le même geste que le déploiement, dans l'autre sens**. C'est ce qui le
# rend fiable : il n'emprunte pas un chemin de secours rarement exercé, il refait exactement ce
# que fait un déploiement normal à sa dernière étape.
#
# Ce qu'il ne fait pas, délibérément : **annuler les migrations**. Une migration appliquée est un
# fait ; la défaire automatiquement détruirait des données. Le produit n'écrit que des migrations
# additives et compatibles avec la version précédente, précisément pour que le retour arrière du
# code suffise. Une migration destructrice exige une décision humaine, décrite dans
# `docs/ops/deployment.md`.
#
# Usage :
#   rollback.sh <racine> [release-cible]
#   rollback.sh /srv/staffptr/production                    # release précédente
#   rollback.sh /srv/staffptr/production /srv/.../20260817…  # release nommée
set -euo pipefail

APP_ROOT="${1:?Racine de déploiement attendue}"
TARGET="${2:-}"

RELEASES_DIR="${APP_ROOT}/releases"
CURRENT_LINK="${APP_ROOT}/current"

log() { printf '\n\033[1m▶ %s\033[0m\n' "$*"; }

CURRENT_RELEASE="$(readlink -f "${CURRENT_LINK}" 2>/dev/null || true)"

if [ -z "${TARGET}" ]; then
  # La release précédente est la plus récente qui n'est pas la courante.
  TARGET="$(ls -1dt "${RELEASES_DIR}"/*/ 2>/dev/null \
    | sed 's:/*$::' \
    | grep -v -F -x "${CURRENT_RELEASE}" \
    | head -n 1 || true)"
fi

if [ -z "${TARGET}" ] || [ ! -d "${TARGET}" ]; then
  echo "✗ Aucune release de repli disponible. Intervention humaine requise."
  exit 1
fi

log "Retour arrière : ${CURRENT_RELEASE:-aucune} → ${TARGET}"
ln -sfn "${TARGET}" "${CURRENT_LINK}.tmp"
mv -Tf "${CURRENT_LINK}.tmp" "${CURRENT_LINK}"

log "Rechargement PHP-FPM et redémarrage des workers"
sudo systemctl reload "php${PHP_VERSION:-8.3}-fpm"
cd "${TARGET}"
php artisan queue:restart

log "Vérification après retour arrière"
if ! curl -fsS --max-time 15 "${HEALTH_URL:-http://127.0.0.1/up}" >/dev/null; then
  echo "✗ /up ne répond toujours pas après retour arrière. Escalade immédiate (voir docs/ops/incident.md)."
  exit 1
fi

log "Retour arrière terminé sur ${TARGET}."
