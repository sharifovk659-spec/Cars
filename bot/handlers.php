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

    // One photo: normal large preview + buttons.
    if ($count === 1) {
        botDeliverPhoto($client, $chatId, $imagePaths[0], $caption, [
            'reply_markup' => $miniAppKeyboard,
        ]);
        return;
    }

    // 2+ photos as one album so left/right swipe stays on this VIN only (photos, not documents).
    if (botSendCarPhotoAlbums($client, $chatId, $imagePaths, $caption)) {
        botDeliverMessage($client, $chatId, '📱 Mini App', [
            'reply_markup' => $miniAppKeyboard,
        ]);
        return;
    }

    // Album failed — show cover + button to load the rest.
    $keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => 'Дидани ҳамаи суратҳо', 'callback_data' => 'photos:' . $carId]],
            $miniAppRow[0],
        ],
    ], JSON_UNESCAPED_UNICODE);

    botDeliverPhoto($client, $chatId, $imagePaths[0], $caption, [
        'reply_markup' => $keyboard,
    ]);
}

/**
 * Send car photos as Telegram photo albums (max 10 per group).
 * Caption only on the first photo of the first album.
 *
 * @param list<string> $paths
 */
function botSendCarPhotoAlbums(
    TelegramClient $client,
    int|string $chatId,
    array $paths,
    string $caption = ''
): bool {
    $paths = array_values(array_filter($paths, static fn ($p) => is_string($p) && $p !== ''));
    if ($paths === []) {
        return false;
    }

    $chunks = array_chunk($paths, 10);
    $sentAny = false;

    foreach ($chunks as $chunkIndex => $chunk) {
        $chunkCaption = $chunkIndex === 0 ? $caption : '';
        $result = $client->sendMediaGroup($chatId, $chunk, $chunkCaption);
        $status = is_array($result)
            ? (string) ($result['status'] ?? 'failed')
            : ($result !== null ? 'ok' : 'failed');

        if ($status === 'ok' || $status === 'uncertain') {
            $sentAny = true;
            continue;
        }

        // First album failed — let caller use cover fallback.
        if (!$sentAny) {
            return false;
        }

        // Later chunk failed — send remaining as single photos (last resort).
        foreach ($chunk as $path) {
            if (botDeliverPhoto($client, $chatId, $path, '')) {
                $sentAny = true;
            }
            usleep(250000);
        }
    }

    return $sentAny;
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

    // Cover already shown on the main card — send the rest as album, no caption.
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

    if (botSendCarPhotoAlbums($client, $chatId, $paths, '')) {
        return;
    }

    $sentAny = false;
    foreach ($paths as $path) {
        if (botDeliverPhoto($client, $chatId, $path, '')) {
            $sentAny = true;
        }
        usleep(250000);
    }

    if (!$sentAny) {
        $client->sendMessage($chatId, noPhotoMessage());
    }
}
