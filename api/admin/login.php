<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/admin/includes/auth.php';
require_once dirname(__DIR__, 2) . '/admin/includes/i18n.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-Telegram-Init-Data');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** @return array<string, mixed> */
function miniAppAdminReadBody(): array
{
    $raw = file_get_contents('php://input');

    if (is_string($raw) && trim($raw) !== '') {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return is_array($_POST) ? $_POST : [];
}

function isMiniAppAdminRequest(): bool
{
    if (trim((string) ($_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? '')) !== '') {
        return true;
    }

    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

    return str_contains($referer, '/miniapp/');
}

$body = miniAppAdminReadBody();
$login = trim((string) ($body['login'] ?? $body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');
$remember = !empty($body['remember']) || isMiniAppAdminRequest();

$result = loginAdmin($login, $password, $remember);

if (!$result['success']) {
    http_response_code(401);
    echo json_encode([
        'ok'    => false,
        'error' => $result['error'] ?? __('auth.error_generic'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$admin = getCurrentAdmin();

if ($admin === null) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => __('auth.error_generic'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$adminId = (int) ($admin['id'] ?? 0);
$bridge = $adminId > 0 ? createMiniAppAdminBridgeToken($adminId) : '';

echo json_encode([
    'ok'       => true,
    'admin'    => [
        'id'       => $adminId,
        'name'     => (string) ($admin['full_name'] ?: $admin['username'] ?? ''),
        'email'    => (string) ($admin['email'] ?? ''),
        'username' => (string) ($admin['username'] ?? ''),
    ],
    'redirect' => $bridge !== '' ? 'admin.php?bridge=' . rawurlencode($bridge) : 'admin.php',
], JSON_UNESCAPED_UNICODE);
