<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/admin/includes/auth.php';
require_once dirname(__DIR__, 2) . '/admin/includes/i18n.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
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

/** @var array<string, mixed>|null $body */
$body = json_decode((string) file_get_contents('php://input'), true);
$login = trim((string) ($body['login'] ?? $body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');
$remember = !empty($body['remember']);

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

echo json_encode([
    'ok'    => true,
    'admin' => [
        'id'        => (int) ($admin['id'] ?? 0),
        'name'      => (string) ($admin['full_name'] ?: $admin['username'] ?? ''),
        'email'     => (string) ($admin['email'] ?? ''),
        'username'  => (string) ($admin['username'] ?? ''),
    ],
], JSON_UNESCAPED_UNICODE);
