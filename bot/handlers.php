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

    // Main VIN image: large zoom, no left/right gallery buttons (isolated from Photos).
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

    if (count($paths) === 1) {
        botDeliverPhoto($client, $chatId, $paths[0], '');
        return;
    }

    // Album (media group), no caption — text already on the main card.
    $chunks = array_chunk($paths, 10);
    $sentAny = false;

    foreach ($chunks as $chunk) {
        $result = $client->sendMediaGroup($chatId, $chunk, '');
        $status = is_array($result)
            ? (string) ($result['status'] ?? 'failed')
            : ($result !== null ? 'ok' : 'failed');

        if ($status === 'ok' || $status === 'uncertain') {
            $sentAny = true;
            continue;
        }

        foreach ($chunk as $path) {
            if (botDeliverPhoto($client, $chatId, $path, '')) {
                $sentAny = true;
            }
            usleep(250000);
        }
    }

    if (!$sentAny) {
        $client->sendMessage($chatId, noPhotoMessage());
    }
}
