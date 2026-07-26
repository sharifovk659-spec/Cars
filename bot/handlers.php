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

    $options = ['reply_markup' => $keyboard];
    $fileName = 'car_' . preg_replace('/[^A-Za-z0-9_-]+/', '', $vin) . '_main.jpg';

    // Document (not photo) = zoom opens only this main image, no left/right gallery swipe.
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

    $caption = buildCarCaption($car);

    if (count($paths) === 1) {
        botDeliverPhoto($client, $chatId, $paths[0], $caption);
        return;
    }

    // Send albums in chunks of 10; fall back to one-by-one if album fails.
    $chunks = array_chunk($paths, 10);
    $sentAny = false;

    foreach ($chunks as $chunkIndex => $chunk) {
        $chunkCaption = $chunkIndex === 0 ? $caption : '';
        $ok = $client->sendMediaGroup($chatId, $chunk, $chunkCaption);

        if ($ok !== null) {
            $sentAny = true;
            continue;
        }

        foreach ($chunk as $photoIndex => $path) {
            $photoCaption = ($chunkIndex === 0 && $photoIndex === 0) ? $caption : '';
            if (botDeliverPhoto($client, $chatId, $path, $photoCaption)) {
                $sentAny = true;
            }
            usleep(250000);
        }
    }

    if (!$sentAny) {
        $client->sendMessage($chatId, noPhotoMessage() . "\n\n" . strip_tags($caption));
    }
}
