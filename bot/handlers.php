<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/TelegramClient.php';

/**
 * @param array<string, mixed> $car
 */
function sendCarToChat(TelegramClient $client, int|string $chatId, array $car): void
{
    $caption = buildCarCaption($car);
    $carId = (int) $car['id'];
    $vin = (string) $car['vin_code'];
    $imagePaths = getCarImagePaths($carId);
    $count = count($imagePaths);

    $miniAppBtn = miniAppWebAppButton($carId, $vin);
    $miniAppRow = [$miniAppBtn];

    if ($count === 0) {
        $text = noPhotoMessage() . "\n\n" . $caption;
        botDeliverMessage($client, $chatId, $text, [
            'reply_markup' => json_encode(['inline_keyboard' => [$miniAppRow]], JSON_UNESCAPED_UNICODE),
        ]);
        return;
    }

    $keyboardRows = [];
    if ($count > 1) {
        $keyboardRows[] = [[
            'text'          => 'Дидани ҳамаи суратҳо',
            'callback_data' => 'photos:' . $carId,
        ]];
    }
    $keyboardRows[] = $miniAppRow;

    $options = [
        'reply_markup' => json_encode(['inline_keyboard' => $keyboardRows], JSON_UNESCAPED_UNICODE),
    ];

    // Document (not sendPhoto) — Telegram will not mix this car into other cars' photo swipe gallery.
    $fileName = 'car_' . preg_replace('/[^A-Za-z0-9_-]+/', '', $vin) . '_main.jpg';
    if ($client->sendIsolatedImage($chatId, $imagePaths[0], $caption, $options, $fileName) !== null) {
        return;
    }

    $fallback = $options;
    unset($fallback['reply_markup']);
    if ($client->sendIsolatedImage($chatId, $imagePaths[0], strip_tags($caption), $fallback, $fileName) !== null) {
        return;
    }

    botDeliverMessage($client, $chatId, $caption, $options);
}

function sendAllCarPhotos(TelegramClient $client, int|string $chatId, int $carId): void
{
    $car = findCarById($carId);

    if ($car === null) {
        $client->sendMessage($chatId, notFoundMessage((string) $carId));
        return;
    }

    $paths = getCarImagePaths($carId);
    $vin = (string) ($car['vin_code'] ?? '');

    if ($paths === []) {
        $client->sendMessage($chatId, noPhotoMessage());
        return;
    }

    $caption = buildCarCaption($car);
    $safeVin = preg_replace('/[^A-Za-z0-9_-]+/', '', $vin) ?: (string) $carId;

    $sentAny = false;
    foreach ($paths as $index => $path) {
        $photoCaption = $index === 0 ? $caption : '';
        $fileName = 'car_' . $safeVin . '_' . ($index + 1) . '.jpg';
        $ok = $client->sendIsolatedImage($chatId, $path, $photoCaption, [], $fileName);
        if ($ok === null && $photoCaption !== '') {
            $ok = $client->sendIsolatedImage($chatId, $path, strip_tags($photoCaption), [], $fileName);
        }
        if ($ok === null) {
            $ok = $client->sendIsolatedImage($chatId, $path, '', [], $fileName);
        }
        if ($ok !== null) {
            $sentAny = true;
        }
        usleep(280000);
    }

    if (!$sentAny) {
        $client->sendMessage($chatId, noPhotoMessage() . "\n\n" . strip_tags($caption));
    }
}
