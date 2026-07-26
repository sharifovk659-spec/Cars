<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/TelegramClient.php';

/**
 * One car = one chat message (photo + caption + buttons).
 * Never use media albums for car cards — albums swipe left/right on phones.
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

    $miniAppRow = [miniAppWebAppButton($carId, $vin)];
    $miniAppKeyboard = json_encode(['inline_keyboard' => [$miniAppRow]], JSON_UNESCAPED_UNICODE);

    if ($count === 0) {
        botDeliverMessage($client, $chatId, noPhotoMessage() . "\n\n" . $caption, [
            'reply_markup' => $miniAppKeyboard,
        ]);
        return;
    }

    // Always one photo message for the card (same design as Telegram preview).
    $keyboardRows = [];
    if ($count > 1) {
        $keyboardRows[] = [[
            'text'          => 'Дидани ҳамаи суратҳо',
            'callback_data' => 'photos:' . $carId,
        ]];
    }
    $keyboardRows[] = $miniAppRow;

    botDeliverPhoto($client, $chatId, $imagePaths[0], $caption, [
        'reply_markup' => json_encode(['inline_keyboard' => $keyboardRows], JSON_UNESCAPED_UNICODE),
    ]);
}

/**
 * Send each found car as its own vertical chat message (no album / no swipe between cars).
 *
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
 * Photos of ONE selected car only — each photo is a separate message (vertical scroll).
 * No sendMediaGroup: Telegram albums force horizontal swipe and confuse users on phones.
 */
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
    $header = '📷 <b>Ҳамаи суратҳо</b> · VIN <code>'
        . htmlspecialchars((string) $car['vin_code'], ENT_QUOTES, 'UTF-8')
        . '</code>';

    botDeliverMessage($client, $chatId, $header);

    $sentAny = false;
    foreach ($paths as $index => $path) {
        // Caption only on first photo so design stays readable; rest are plain photos of this car.
        $photoCaption = $index === 0 ? $caption : '';
        if (botDeliverPhoto($client, $chatId, $path, $photoCaption)) {
            $sentAny = true;
        }
        usleep(300000);
    }

    if (!$sentAny) {
        $client->sendMessage($chatId, noPhotoMessage() . "\n\n" . strip_tags($caption));
    }
}
