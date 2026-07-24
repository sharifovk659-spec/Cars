#!/usr/bin/env bash
#
# Create private production config on Hostinger (outside public_html).
# Does NOT overwrite existing files.
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PRIVATE_CONFIG="${1:-/home/u417315406/domains/inovaauto.com/private/carsbot}"
TEMPLATES="${SCRIPT_DIR}/templates/private"

log() {
    echo "[setup] $*"
}

mkdir -p "${PRIVATE_CONFIG}/backups"
chmod 700 "${PRIVATE_CONFIG}"

for file in database.php app.local.php telegram.local.php; do
    target="${PRIVATE_CONFIG}/${file}"
    source="${TEMPLATES}/${file}"

    if [[ -f "${target}" ]]; then
        log "SKIP (exists): ${target}"
    else
        cp "${source}" "${target}"
        chmod 600 "${target}"
        log "CREATED: ${target}"
    fi
done

log "Edit credentials in ${PRIVATE_CONFIG}/ before deploy."
log "Files in public_html are NOT touched by this script."
