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

    // Document (not photo) = zoom opens only this VIN image, no left/right into other cars.
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

    // Cover already on the main card — do not resend (avoids duplicates).
    // Never attach caption — text already on the main card.
    $vinSafe = preg_replace('/[^A-Za-z0-9_-]+/', '', (string) ($car['vin_code'] ?? 'car')) ?: 'car';

    if (count($paths) > 1) {
        $paths = array_values(array_slice($paths, 1));
    }

    if (count($paths) === 1) {
        botDeliverIsolatedImage($client, $chatId, $paths[0], '', 'car_' . $vinSafe . '_1.jpg');
        return;
    }

    // Document album: swipe only within this VIN, not previous car photos in chat.
    $chunks = array_chunk($paths, 10);
    $sentAny = false;

    foreach ($chunks as $chunkIndex => $chunk) {
        $result = $client->sendMediaGroup(
            $chatId,
            $chunk,
            '',
            [],
            true,
            'car_' . $vinSafe . '_p' . ($chunkIndex + 1)
        );
        $status = is_array($result)
            ? (string) ($result['status'] ?? 'failed')
            : ($result !== null ? 'ok' : 'failed');

        // ok = delivered; uncertain = timeout (may already be delivered — do not retry)
        if ($status === 'ok' || $status === 'uncertain') {
            $sentAny = true;
            continue;
        }

        foreach ($chunk as $photoIndex => $path) {
            $name = 'car_' . $vinSafe . '_' . (($chunkIndex * 10) + $photoIndex + 1) . '.jpg';
            if (botDeliverIsolatedImage($client, $chatId, $path, '', $name)) {
                $sentAny = true;
            }
            usleep(250000);
        }
    }

    if (!$sentAny) {
        $client->sendMessage($chatId, noPhotoMessage());
    }
}
