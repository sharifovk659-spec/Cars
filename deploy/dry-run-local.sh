#!/usr/bin/env bash
#
# Local dry-run validation (Windows Git Bash / Linux)
# Simulates deploy plan without touching production server.
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

echo "=============================================="
echo " Telegram Cars — LOCAL DRY-RUN REPORT"
echo "=============================================="
echo ""
echo "Project: ${PROJECT_ROOT}"
echo "Target:  /home/u417315406/domains/inovaauto.com/public_html/carsbot"
echo "URL:     https://carsbot.inovaauto.com"
echo ""

echo "--- 1. Git tracking (secrets excluded) ---"
if command -v git >/dev/null 2>&1; then
    if [[ -d "${PROJECT_ROOT}/.git" ]]; then
        git -C "${PROJECT_ROOT}" status --short
    else
        echo "Git not initialized. Run: git init && git add ."
    fi
else
    echo "git not available"
fi

echo ""
echo "--- 2. Files that must NOT go to Git ---"
for f in config/database.local.php config/telegram.local.php deploy/deploy.env; do
    if [[ -f "${PROJECT_ROOT}/${f}" ]]; then
        echo "  [local only] ${f}"
    fi
done

echo ""
echo "--- 3. Required deploy files ---"
required=(
    deploy/deploy.sh
    deploy/migrate.php
    deploy/post-deploy-check.sh
    deploy/setup-private-config.sh
    deploy/templates/private/database.php
    api/telegram/webhook.php
    database/schema_install.sql
    config/bootstrap.php
)
for f in "${required[@]}"; do
    if [[ -f "${PROJECT_ROOT}/${f}" ]]; then
        echo "  [ok] ${f}"
    else
        echo "  [MISSING] ${f}"
    fi
done

echo ""
echo "--- 4. Migration dry-run (local DB) ---"
if command -v php >/dev/null 2>&1; then
    php "${PROJECT_ROOT}/deploy/migrate.php" --dry-run || true
else
    echo "PHP not in PATH — skip local migration dry-run"
fi

echo ""
echo "--- 5. Server deploy simulation (DRY_RUN=1) ---"
DRY_RUN=1 DEPLOY_METHOD=git bash "${SCRIPT_DIR}/deploy.sh"

echo ""
echo "--- 6. Production URLs to test after deploy ---"
echo "  Admin:   https://carsbot.inovaauto.com/admin/"
echo "  MiniApp: https://carsbot.inovaauto.com/miniapp/"
echo "  Webhook: https://carsbot.inovaauto.com/api/telegram/webhook.php"
echo ""
echo "NO deploy, import, or reset was performed."
echo "Set DRY_RUN=0 in deploy/deploy.env to run live deploy (with your approval)."
