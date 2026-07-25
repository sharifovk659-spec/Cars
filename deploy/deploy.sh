#!/usr/bin/env bash
#
# Telegram Cars — Deploy script for Hostinger
# Default: DRY_RUN=1 (no changes). Set DRY_RUN=0 for real deploy.
#
# Usage:
#   bash deploy/deploy.sh                  # dry-run
#   DRY_RUN=0 bash deploy/deploy.sh        # real deploy (requires deploy.env)
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

ENV_FILE="${SCRIPT_DIR}/deploy.env"
if [[ -f "${ENV_FILE}" ]]; then
    # shellcheck disable=SC1090
    source "${ENV_FILE}"
fi

DEPLOY_PATH="${DEPLOY_PATH:-/home/u417315406/domains/inovaauto.com/public_html/carsbot}"
PRIVATE_CONFIG="${PRIVATE_CONFIG:-/home/u417315406/domains/inovaauto.com/private/carsbot}"
BACKUP_DIR="${BACKUP_DIR:-${PRIVATE_CONFIG}/backups}"
APP_URL="${APP_URL:-https://carsbot.inovaauto.com}"
DRY_RUN="${DRY_RUN:-1}"
DEPLOY_METHOD="${DEPLOY_METHOD:-git}"
GIT_REMOTE="${GIT_REMOTE:-origin}"
GIT_BRANCH="${GIT_BRANCH:-main}"
DB_HOST="${DB_HOST:-localhost}"

RSYNC_EXCLUDES=(
    "--exclude=.git/"
    "--exclude=uploads/cars/*"
    "--exclude=config/database.local.php"
    "--exclude=config/telegram.local.php"
    "--exclude=config/app.local.php"
    "--exclude=.env"
    "--exclude=deploy/deploy.env"
    "--exclude=deploy/backups/"
)

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

run_cmd() {
    if [[ "${DRY_RUN}" == "1" ]]; then
        log "[DRY-RUN] $*"
    else
        log "[RUN] $*"
        eval "$@"
    fi
}

preflight() {
    log "=== Telegram Cars Deploy ==="
    log "Mode: $([[ "${DRY_RUN}" == "1" ]] && echo 'DRY-RUN' || echo 'LIVE')"
    log "Deploy path: ${DEPLOY_PATH}"
    log "Private config: ${PRIVATE_CONFIG}"
    log "App URL: ${APP_URL}"
    log "Method: ${DEPLOY_METHOD}"

    if [[ "${DRY_RUN}" == "0" ]]; then
        if [[ ! -d "${PRIVATE_CONFIG}" ]]; then
            log "ERROR: Private config directory missing: ${PRIVATE_CONFIG}"
            log "Run: bash deploy/setup-private-config.sh"
            exit 1
        fi
        if [[ ! -f "${PRIVATE_CONFIG}/database.php" ]]; then
            log "ERROR: ${PRIVATE_CONFIG}/database.php not found"
            exit 1
        fi
    else
        log "Preflight: dry-run — skipping server file checks"
    fi
}

backup_files() {
    local stamp
    stamp="$(date '+%Y%m%d_%H%M%S')"
    local archive="${BACKUP_DIR}/files_${stamp}.tar.gz"

    run_cmd "mkdir -p '${BACKUP_DIR}'"

    if [[ -d "${DEPLOY_PATH}" ]]; then
        run_cmd "tar -czf '${archive}' -C '$(dirname "${DEPLOY_PATH}")' '$(basename "${DEPLOY_PATH}")' --exclude='uploads/cars/*'"
        log "File backup: ${archive}"
    else
        log "Skip file backup — deploy path does not exist yet"
    fi
}

backup_database() {
    local stamp
    stamp="$(date '+%Y%m%d_%H%M%S')"
    local dump="${BACKUP_DIR}/db_${stamp}.sql.gz"

    if [[ -z "${DB_NAME:-}" || -z "${DB_USER:-}" ]]; then
        log "Skip DB backup — DB_NAME/DB_USER not set in deploy.env"
        return
    fi

    if [[ "${DRY_RUN}" == "1" ]]; then
        log "[DRY-RUN] mysqldump -h '${DB_HOST}' -u '${DB_USER}' '${DB_NAME}' | gzip > '${dump}'"
        return
    fi

    if ! command -v mysqldump >/dev/null 2>&1; then
        log "WARNING: mysqldump not found — skipping database backup"
        return
    fi

    run_cmd "mkdir -p '${BACKUP_DIR}'"

    if [[ -n "${DB_PASSWORD:-}" ]]; then
        run_cmd "mysqldump -h '${DB_HOST}' -u '${DB_USER}' -p'${DB_PASSWORD}' '${DB_NAME}' | gzip > '${dump}'"
    else
        run_cmd "mysqldump -h '${DB_HOST}' -u '${DB_USER}' '${DB_NAME}' | gzip > '${dump}'"
    fi

    log "Database backup: ${dump}"
}

deploy_git() {
    if [[ -d "${DEPLOY_PATH}/.git" ]]; then
        run_cmd "cd '${DEPLOY_PATH}' && git fetch '${GIT_REMOTE}' && git checkout '${GIT_BRANCH}' && git pull '${GIT_REMOTE}' '${GIT_BRANCH}'"
    else
        log "Git repo not found at ${DEPLOY_PATH} — first deploy needs manual git clone"
        run_cmd "mkdir -p '${DEPLOY_PATH}'"
        log "[DRY-RUN] git clone <repo-url> '${DEPLOY_PATH}'"
    fi
}

deploy_rsync() {
    local source="${RSYNC_SOURCE:-${PROJECT_ROOT}}"

    run_cmd "mkdir -p '${DEPLOY_PATH}'"
    run_cmd "rsync -av --delete ${RSYNC_EXCLUDES[*]} '${source}/' '${DEPLOY_PATH}/'"
}

run_migrations() {
    local migrate_path="${DEPLOY_PATH}/deploy/migrate.php"

    if [[ ! -f "${migrate_path}" ]]; then
        migrate_path="${PROJECT_ROOT}/deploy/migrate.php"
    fi

    if [[ "${DRY_RUN}" == "1" ]]; then
        run_cmd "php '${migrate_path}' --dry-run"
    else
        run_cmd "php '${migrate_path}'"
    fi
}

ensure_webhook() {
    local script="${DEPLOY_PATH}/deploy/ensure_webhook.php"

    if [[ ! -f "${script}" ]]; then
        script="${SCRIPT_DIR}/ensure_webhook.php"
    fi

    if [[ "${DRY_RUN}" == "1" ]]; then
        log "[DRY-RUN] php '${script}'"
        return
    fi

    run_cmd "php '${script}'"
}

run_car_retention_cleanup() {
    local script="${DEPLOY_PATH}/deploy/cleanup_old_cars.php"

    if [[ ! -f "${script}" ]]; then
        script="${SCRIPT_DIR}/cleanup_old_cars.php"
    fi

    if [[ "${DRY_RUN}" == "1" ]]; then
        log "[DRY-RUN] php '${script}'"
        return
    fi

    run_cmd "php '${script}'"
}

post_deploy_checks() {
    if [[ "${DRY_RUN}" == "1" ]]; then
        log "[DRY-RUN] bash '${SCRIPT_DIR}/post-deploy-check.sh' '${APP_URL}'"
        return
    fi

    bash "${SCRIPT_DIR}/post-deploy-check.sh" "${APP_URL}"
}

ensure_uploads() {
    run_cmd "mkdir -p '${DEPLOY_PATH}/uploads/cars'"
    run_cmd "chmod 755 '${DEPLOY_PATH}/uploads' '${DEPLOY_PATH}/uploads/cars' || true"
}

main() {
    preflight
    backup_files
    backup_database

    case "${DEPLOY_METHOD}" in
        git) deploy_git ;;
        rsync) deploy_rsync ;;
        *) log "Unknown DEPLOY_METHOD: ${DEPLOY_METHOD}"; exit 1 ;;
    esac

    ensure_uploads
    run_migrations
    run_car_retention_cleanup
    ensure_webhook
    post_deploy_checks

    log "=== Deploy finished ($([[ "${DRY_RUN}" == "1" ]] && echo 'dry-run' || echo 'live')) ==="

    if [[ "${DRY_RUN}" == "1" ]]; then
        log ""
        log "To run LIVE deploy: copy deploy/deploy.env.example → deploy/deploy.env,"
        log "fill DB credentials, set DRY_RUN=0, then: bash deploy/deploy.sh"
    fi
}

main "$@"
