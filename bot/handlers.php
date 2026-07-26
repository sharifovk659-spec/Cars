<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/TelegramClient.php';

/**
 * One car = one isolated image message (document), so Telegram Photos swipe
 * cannot jump to another VIN's photos in the same chat.
 *
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

    if ($count === 0) {
        botDeliverMessage($client, $chatId, noPhotoMessage() . "\n\n" . $caption, [
            'reply_markup' => json_encode(['inline_keyboard' => [[$miniAppBtn]]], JSON_UNESCAPED_UNICODE),
        ]);
        return;
    }

    $keyboardRows = [[$miniAppBtn]];
    if ($count > 1) {
        array_unshift($keyboardRows, [[
            'text'          => 'Дидани ҳамаи суратҳо',
            'callback_data' => 'photos:' . $carId,
        ]]);
    }

    $options = [
        'reply_markup' => json_encode(['inline_keyboard' => $keyboardRows], JSON_UNESCAPED_UNICODE),
    ];

    $fileName = 'car_' . preg_replace('/[^A-Za-z0-9_-]+/', '', $vin) . '_main.jpg';
    if ($client->sendIsolatedImage($chatId, $imagePaths[0], $caption, $options, $fileName) !== null) {
        return;
    }

    $fallback = $options;
    unset($fallback['reply_markup']);
    if ($client->sendIsolatedImage($chatId, $imagePaths[0], strip_tags($caption), $fallback, $fileName) !== null) {
        return;
    }

    // Never fall back to sendPhoto — that re-enables gallery swipe on zoom.
    botDeliverMessage($client, $chatId, $caption, $options);
}

/**
 * Send ONLY this car's photos as isolated documents (no swipe to other VINs).
 */
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

        if ($ok !== null) {
            $sentAny = true;
        }

        if ($index < count($paths) - 1) {
            usleep(250000);
        }
    }

    if (!$sentAny) {
        $client->sendMessage($chatId, noPhotoMessage() . "\n\n" . strip_tags($caption));
    }
}
