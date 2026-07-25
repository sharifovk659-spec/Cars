<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/admin/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

startSession();

$admin = getCurrentAdmin();

if ($admin === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'authenticated' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok'            => true,
    'authenticated' => true,
    'admin'         => [
        'id'       => (int) $admin['id'],
        'name'     => (string) ($admin['full_name'] ?: $admin['username']),
        'email'    => (string) $admin['email'],
        'username' => (string) $admin['username'],
    ],
], JSON_UNESCAPED_UNICODE);
