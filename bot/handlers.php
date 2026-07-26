<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/TelegramClient.php';

/**
 * One car = one chat message (isolated image + caption + buttons).
 * Isolated document keeps Telegram Photos swipe on THIS car only (no other VINs).
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
    $photosBtn = [
        'text'          => 'Дидани ҳамаи суратҳо',
        'callback_data' => 'photos:' . $carId,
    ];

    if ($count === 0) {
        botDeliverMessage($client, $chatId, noPhotoMessage() . "\n\n" . $caption, [
            'reply_markup' => json_encode(['inline_keyboard' => [[$miniAppBtn]]], JSON_UNESCAPED_UNICODE),
        ]);
        return;
    }

    $keyboardRows = [
        [$photosBtn],
        [$miniAppBtn],
    ];

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

    botDeliverMessage($client, $chatId, $caption, $options);
}

/**
 * @param list<array<string, mixed>> $cars
 */
function sendCarsToChat(TelegramClient $client, int|string $chatId, array $cars): void
{
    $total = count($cars);
    foreach ($cars as $index => $car) {
        sendCarToChat($client, $chatId, $car);
        if ($index < $total - 1) {
            usleep(350000);
        }
    }
}

/**
 * Send ONLY this car's photos as isolated documents (no left/right to other VINs).
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

    $header = '📷 <b>Ҳамаи суратҳо</b> · VIN <code>'
        . htmlspecialchars($vin, ENT_QUOTES, 'UTF-8')
        . '</code>';
    botDeliverMessage($client, $chatId, $header, [
        'reply_markup' => json_encode([
            'inline_keyboard' => [[miniAppWebAppButton($carId, $vin)]],
        ], JSON_UNESCAPED_UNICODE),
    ]);

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
        $client->sendMessage(
            $chatId,
            noPhotoMessage() . "\n\n" . strip_tags($caption),
            [
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[miniAppWebAppButton($carId, $vin)]],
                ], JSON_UNESCAPED_UNICODE),
            ]
        );
    }
}
