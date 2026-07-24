#!/usr/bin/env bash
#
# Post-deploy smoke tests
# Usage: bash deploy/post-deploy-check.sh [APP_URL]
#

set -euo pipefail

APP_URL="${1:-https://carsbot.inovaauto.com}"
APP_URL="${APP_URL%/}"

pass=0
fail=0

check_url() {
    local name="$1"
    local url="$2"
    local expected="${3:-200}"
    local method="${4:-GET}"

    local code
    if [[ "${method}" == "POST" ]]; then
        code="$(curl -sS -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -d '{}' "${url}" || echo '000')"
    else
        code="$(curl -sS -o /dev/null -w '%{http_code}' "${url}" || echo '000')"
    fi

    if [[ "${code}" == "${expected}" ]]; then
        echo "[PASS] ${name} — HTTP ${code} — ${url}"
        pass=$((pass + 1))
    else
        echo "[FAIL] ${name} — HTTP ${code} (expected ${expected}) — ${url}"
        fail=$((fail + 1))
    fi
}

echo "=== Post-deploy checks: ${APP_URL} ==="

check_url "Admin login" "${APP_URL}/admin/login.php" "200"
check_url "Mini App" "${APP_URL}/miniapp/index.html" "200"
check_url "API index" "${APP_URL}/api/index.php" "200"
check_url "Webhook" "${APP_URL}/api/telegram/webhook.php" "200" "POST"

echo "=== Results: ${pass} passed, ${fail} failed ==="

if [[ "${fail}" -gt 0 ]]; then
    exit 1
fi
