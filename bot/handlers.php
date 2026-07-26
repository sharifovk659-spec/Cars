<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/TelegramClient.php';

/**
 * One car = one chat message (image + caption + buttons).
 * Images are sent as isolated documents so Telegram Photos viewer
 * cannot swipe into other cars from earlier VIN searches.
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

    $keyboardRows = [];
    if ($count > 1) {
        // Open Mini App for THIS vin only — no extra chat photos that mix galleries.
        $keyboardRows[] = [[
            'text'    => 'Дидани ҳамаи суратҳо',
            'web_app' => ['url' => miniAppCarUrl($vin) . '#gallery'],
        ]];
    }
    $keyboardRows[] = [$miniAppBtn];

    botDeliverPhoto(
        $client,
        $chatId,
        $imagePaths[0],
        $caption,
        ['reply_markup' => json_encode(['inline_keyboard' => $keyboardRows], JSON_UNESCAPED_UNICODE)],
        'car_' . $carId . '_main.jpg'
    );
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
 * Legacy callback photos:ID — send ONLY this car's images as isolated documents.
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

    // Prefer Mini App for this VIN only (no chat gallery mixing).
    if ($vin !== '') {
        $opened = botDeliverMessage($client, $chatId, '📷 Суратҳои ин мошинро дар Mini App кушоед:', [
            'reply_markup' => json_encode([
                'inline_keyboard' => [[
                    [
                        'text'    => 'Дидани ҳамаи суратҳо',
                        'web_app' => ['url' => miniAppCarUrl($vin) . '#gallery'],
                    ],
                ]],
            ], JSON_UNESCAPED_UNICODE),
        ]);
        if ($opened) {
            return;
        }
    }

    $caption = buildCarCaption($car);
    $header = '📷 <b>Ҳамаи суратҳо</b> · VIN <code>'
        . htmlspecialchars($vin, ENT_QUOTES, 'UTF-8')
        . '</code>';

    botDeliverMessage($client, $chatId, $header);

    $sentAny = false;
    foreach ($paths as $index => $path) {
        $photoCaption = $index === 0 ? $caption : '';
        if (botDeliverPhoto(
            $client,
            $chatId,
            $path,
            $photoCaption,
            [],
            'car_' . $carId . '_' . ($index + 1) . '.jpg'
        )) {
            $sentAny = true;
        }
        usleep(300000);
    }

    if (!$sentAny) {
        $client->sendMessage($chatId, noPhotoMessage() . "\n\n" . strip_tags($caption));
    }
}
