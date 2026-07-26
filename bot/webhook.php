<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/telegram.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/TelegramClient.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/handlers.php';

function webhookAckOk(): void
{
    if (headers_sent()) {
        return;
    }

    http_response_code(200);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'OK';

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

http_response_code(200);

$raw = file_get_contents('php://input');

if ($raw === false || $raw === '') {
    exit('OK');
}

/** @var array<string, mixed>|null $update */
$update = json_decode($raw, true);

if (!is_array($update)) {
    exit('OK');
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

    if ($chatId !== null && str_starts_with($data, 'photos:')) {
        $carId = (int) substr($data, strlen('photos:'));

        if ($carId > 0 && is_array($from)) {
            webhookAckOk();

            try {
                if ($callbackId !== '') {
                    $client->answerCallbackQuery($callbackId, 'Суратҳо фиристода мешаванд…');
                }
                upsertTelegramUser($from);
                sendAllCarPhotos($client, $chatId, $carId);
            } catch (Throwable $e) {
                error_log('Telegram photos callback failed: ' . $e->getMessage());
                $client->sendMessage($chatId, '⚠️ Суратҳоро фиристода нашуд. Лутфан боз такрор кунед.');
            }
        } elseif ($callbackId !== '') {
            $client->answerCallbackQuery($callbackId, 'Хатогии дархост', true);
        }
    } elseif ($callbackId !== '') {
        $client->answerCallbackQuery($callbackId);
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

webhookAckOk();

try {
    $userId = upsertTelegramUser($from);

    if (str_starts_with($text, '/start')) {
        $firstName = (string) ($from['first_name'] ?? '');
        botDeliverMessage($client, $chatId, welcomeMessage($firstName));
        exit;
    }

    if ($text === '') {
        botDeliverMessage($client, $chatId, 'Введите 4 последние символа vinCode машины');
        exit;
    }

    $car = findCarBySearchQuery($text);
    $vinForLog = $car['vin_code'] ?? (preg_match('/^[A-Z0-9]{11,17}$/i', $text) ? strtoupper($text) : null);

    try {
        logTelegramSearch($userId, $text, $vinForLog, $car ? 1 : 0);
    } catch (Throwable $e) {
        error_log('Telegram search log failed: ' . $e->getMessage());
    }

    if ($car === null) {
        botDeliverMessage($client, $chatId, notFoundMessage($text));
        exit;
    }

    sendCarToChat($client, $chatId, $car);
} catch (Throwable $e) {
    error_log('Telegram webhook failed: ' . $e->getMessage());
    botDeliverMessage($client, $chatId, '⚠️ Хатогии система. Лутфан боз такрор кунед.');
}

exit;
