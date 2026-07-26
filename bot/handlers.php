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

    $miniAppRow = [[miniAppWebAppButton($carId, $vin)]];
    $miniAppKeyboard = json_encode(['inline_keyboard' => $miniAppRow], JSON_UNESCAPED_UNICODE);

    if ($count === 0) {
        $text = noPhotoMessage() . "\n\n" . $caption;
        botDeliverMessage($client, $chatId, $text, [
            'reply_markup' => $miniAppKeyboard,
        ]);
        return;
    }

    $keyboard = $miniAppKeyboard;
    if ($count > 1) {
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => 'Дидани ҳамаи суратҳо', 'callback_data' => 'photos:' . $carId]],
                $miniAppRow[0],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    $fileName = 'car_' . preg_replace('/[^A-Za-z0-9_-]+/', '', $vin) . '_main.jpg';
    $options = ['reply_markup' => $keyboard];

    // Isolated image: large preview in chat, but zoom cannot swipe into other VIN photos.
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

    if ($paths === []) {
        $client->sendMessage($chatId, noPhotoMessage());
        return;
    }

    // Cover already on the main card — do not resend.
    if (count($paths) > 1) {
        $paths = array_values(array_slice($paths, 1));
    }

    if ($paths === []) {
        return;
    }

    // Remaining photos as isolated images too — zoom cannot swipe into other VIN photos.
    $vin = preg_replace('/[^A-Za-z0-9_-]+/', '', (string) ($car['vin_code'] ?? '')) ?: (string) $carId;
    $sentAny = false;

    foreach ($paths as $index => $path) {
        $fileName = 'car_' . $vin . '_' . ($index + 2) . '.jpg';
        if ($client->sendIsolatedImage($chatId, $path, '', [], $fileName) !== null) {
            $sentAny = true;
        }
        usleep(200000);
    }

    if (!$sentAny) {
        $client->sendMessage($chatId, noPhotoMessage());
    }
}
