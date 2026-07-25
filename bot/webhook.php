<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/telegram.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/TelegramClient.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/handlers.php';

$raw = file_get_contents('php://input');

if ($raw === false || $raw === '') {
    http_response_code(200);
    exit('OK');
}

/** @var array<string, mixed>|null $update */
$update = json_decode($raw, true);

if (!is_array($update)) {
    http_response_code(200);
    exit('OK');
}

http_response_code(200);
echo 'OK';

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
} else {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

$botToken = getBotToken();

if ($botToken === '') {
    error_log('Telegram bot token is not configured');
    exit('OK');
}

$client = new TelegramClient($botToken);

if (isset($update['callback_query']) && is_array($update['callback_query'])) {
    $callback = $update['callback_query'];
    $callbackId = (string) ($callback['id'] ?? '');
    $data = (string) ($callback['data'] ?? '');
    $chatId = $callback['message']['chat']['id'] ?? null;
    $from = $callback['from'] ?? [];

    if ($callbackId !== '') {
        $client->answerCallbackQuery($callbackId);
    }

    if ($chatId !== null && str_starts_with($data, 'photos:')) {
        $carId = (int) substr($data, 7);

        if ($carId > 0 && is_array($from)) {
            upsertTelegramUser($from);
            sendAllCarPhotos($client, $chatId, $carId);
        }
    }

    exit('OK');
}

$message = $update['message'] ?? null;

if (!is_array($message)) {
    exit('OK');
}

$chatId = $message['chat']['id'] ?? null;
$text = trim($message['text'] ?? '');
$from = $message['from'] ?? [];

if ($chatId === null || !is_array($from)) {
    exit('OK');
}

$userId = upsertTelegramUser($from);

if (str_starts_with($text, '/start')) {
    $firstName = (string) ($from['first_name'] ?? '');
    $client->sendMessage($chatId, welcomeMessage($firstName));
    exit('OK');
}

if ($text === '') {
    $client->sendMessage($chatId, '🔍 Лутфан VIN Code, 4 ё 5 рақами охирин фиристед.');
    exit('OK');
}

$car = findCarBySearchQuery($text);
$vinForLog = $car['vin_code'] ?? (preg_match('/^[A-Z0-9]{11,17}$/i', $text) ? strtoupper($text) : null);

logTelegramSearch($userId, $text, $vinForLog, $car ? 1 : 0);

if ($car === null) {
    $client->sendMessage($chatId, notFoundMessage($text));
    exit('OK');
}

sendCarToChat($client, $chatId, $car);

exit('OK');
