<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/includes/telegram_auth.php';
require_once dirname(__DIR__) . '/bot/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Telegram-Init-Data');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

requireTelegramInitData();

$query = strtoupper(trim($_GET['q'] ?? ''));

if ($query === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'query_required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$car = findCarBySearchQuery($query);

if ($car === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok'  => true,
    'car' => formatCarForApi($car),
], JSON_UNESCAPED_UNICODE);
