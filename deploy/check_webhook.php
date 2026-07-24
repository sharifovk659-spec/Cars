<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../bot/TelegramClient.php';

$client = new TelegramClient(getBotToken());
$info = $client->request('getWebhookInfo', []);

echo json_encode([
    'app_url'       => APP_URL,
    'webhook_info'  => $info,
    'expected_url'  => APP_URL . '/api/telegram/webhook.php',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
