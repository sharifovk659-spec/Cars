<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/telegram.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/includes/image_optimize.php';
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

$vin = strtoupper(trim($_GET['vin'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);
$car = null;

if ($id > 0) {
    $car = findCarById($id);
}

if ($car === null && $vin !== '') {
    $car = findCarBySearchQuery($vin);
}

if ($car === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($vin !== '') {
    $matched = findCarBySearchQuery($vin);
    if ($matched === null || (int) $matched['id'] !== (int) $car['id']) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($id > 0 && (int) $car['id'] !== $id) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$companyLogo = getSetting('company_logo', '');
$logoUrl = $companyLogo ? resolveImagePublicUrl($companyLogo) : null;

echo json_encode([
    'ok'   => true,
    'car'  => formatCarForApi($car),
    'meta' => [
        'company_name' => getSetting('company_name', APP_NAME),
        'company_logo' => $logoUrl,
        'bot_name'     => getSetting('bot_name', APP_NAME),
        'bot_username' => getSetting('bot_username', 'InovaCarsBot'),
    ],
], JSON_UNESCAPED_UNICODE);
