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

    $miniAppKeyboard = json_encode([
        'inline_keyboard' => [[miniAppWebAppButton($carId, $vin)]],
    ], JSON_UNESCAPED_UNICODE);

    if ($count === 0) {
        $text = noPhotoMessage() . "\n\n" . $caption;
        botDeliverMessage($client, $chatId, $text, [
            'reply_markup' => $miniAppKeyboard,
        ]);
        return;
    }

    // One photo: send with Mini App button.
    if ($count === 1) {
        botDeliverPhoto($client, $chatId, $imagePaths[0], $caption, [
            'reply_markup' => $miniAppKeyboard,
        ]);
        return;
    }

    // Several photos: send as Telegram album so swipe stays inside THIS car only
    // (separate photo messages let Telegram media viewer jump to other cars in the chat).
    $sentAlbum = sendCarPhotoAlbums($client, $chatId, $imagePaths, $caption);

    if (!$sentAlbum) {
        // Last resort: first photo + caption, without creating a chain of single photos.
        botDeliverPhoto($client, $chatId, $imagePaths[0], $caption);
    }

    botDeliverMessage($client, $chatId, '📷 ' . $count . ' сурат · Mini App:', [
        'reply_markup' => $miniAppKeyboard,
    ]);
}

/**
 * Send photos as album chunk(s). Returns true if at least one album was delivered.
 *
 * @param list<string> $paths
 */
function sendCarPhotoAlbums(
    TelegramClient $client,
    int|string $chatId,
    array $paths,
    string $caption
): bool {
    $chunks = array_chunk($paths, 10);
    $sentAny = false;

    foreach ($chunks as $chunkIndex => $chunk) {
        $chunkCaption = $chunkIndex === 0 ? $caption : '';
        if ($client->sendMediaGroup($chatId, $chunk, $chunkCaption) !== null) {
            $sentAny = true;
            continue;
        }

        // Retry chunk without caption (HTML caption can break the whole album).
        if ($chunkCaption !== '' && $client->sendMediaGroup($chatId, $chunk, '') !== null) {
            if ($caption !== '') {
                $client->sendMessage($chatId, $caption);
            }
            $sentAny = true;
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

    $caption = buildCarCaption($car);

    if (count($paths) === 1) {
        botDeliverPhoto($client, $chatId, $paths[0], $caption);
        return;
    }

    // Always prefer album delivery — never send one-by-one (avoids swipe to other cars).
    if (!sendCarPhotoAlbums($client, $chatId, $paths, $caption)) {
        $client->sendMessage($chatId, "⚠️ Суратҳои альбом фиристода нашуд.\n\n" . strip_tags($caption));
    }
}
