<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/telegram.php';
require_once dirname(__DIR__) . '/includes/settings.php';

header('Content-Type: application/json; charset=utf-8');

$token = getBotToken();

echo json_encode([
    'service'  => 'telegram-bot',
    'status'   => $token !== '' ? 'configured' : 'token_missing',
    'webhook'  => APP_URL . '/bot/webhook.php',
    'mini_app' => TELEGRAM_MINI_APP_URL,
], JSON_UNESCAPED_UNICODE);
