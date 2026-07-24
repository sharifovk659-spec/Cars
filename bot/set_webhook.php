<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/telegram.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/TelegramClient.php';

header('Content-Type: text/plain; charset=utf-8');

$botToken = getBotToken();

if ($botToken === '') {
    echo "Bot token is empty. Set it in Admin Settings or config/telegram.local.php\n";
    exit(1);
}

$webhookUrl = APP_URL . '/api/telegram/webhook.php';
$client = new TelegramClient($botToken);
$result = $client->request('setWebhook', ['url' => $webhookUrl]);

if ($result) {
    echo "Webhook set to: {$webhookUrl}\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "Failed to set webhook\n";
    exit(1);
}
