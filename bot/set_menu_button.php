<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/telegram.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/TelegramClient.php';

header('Content-Type: application/json; charset=utf-8');

$token = getBotToken();

if ($token === '') {
    echo json_encode(['ok' => false, 'error' => 'token_missing'], JSON_UNESCAPED_UNICODE);
    exit(1);
}

$client = new TelegramClient($token);

$menuButton = [
    'type'    => 'web_app',
    'text'    => miniAppMenuButtonText(),
    'web_app' => ['url' => miniAppHomeUrl()],
];

$result = $client->setChatMenuButton($menuButton);

echo json_encode([
    'ok'          => $result !== null,
    'menu_button' => $menuButton,
    'menu_url'    => miniAppHomeUrl(),
    'car_url_example' => miniAppCarUrl('90775'),
    'telegram_response' => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
