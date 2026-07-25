<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../bot/TelegramClient.php';

$botToken = getBotToken();

if ($botToken === '') {
    fwrite(STDERR, "Bot token is empty — skip webhook ensure.\n");
    exit(0);
}

$expectedUrl = APP_URL . '/api/telegram/webhook.php';
$client = new TelegramClient($botToken);
$info = $client->request('getWebhookInfo', []);
$currentUrl = (string) ($info['result']['url'] ?? '');

if ($currentUrl === $expectedUrl) {
    echo "Webhook OK: {$expectedUrl}\n";
    exit(0);
}

$result = $client->request('setWebhook', ['url' => $expectedUrl]);

if ($result === null) {
    fwrite(STDERR, "Failed to set webhook: {$expectedUrl}\n");
    exit(1);
}

echo "Webhook restored: {$expectedUrl}\n";
if ($currentUrl !== '') {
    echo "Previous URL: {$currentUrl}\n";
}
