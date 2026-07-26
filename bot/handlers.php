<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/TelegramClient.php';

/**
 * One car = one chat message (single photo + caption + buttons).
 * Never send media albums — Telegram mobile media viewer swipes across chat photos.
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

    $viewPhotosBtn = [
        'text'    => 'Дидани ҳамаи суратҳо',
        'web_app' => ['url' => miniAppCarUrl($vin, ['photos' => '1'])],
    ];
    $miniAppBtn = miniAppWebAppButton($carId, $vin);

    $keyboard = [
        'inline_keyboard' => [
            [$viewPhotosBtn],
            [$miniAppBtn],
        ],
    ];

    // Single-photo cars: still show both buttons (photos opens same car page).
    if ($count === 0) {
        botDeliverMessage($client, $chatId, noPhotoMessage() . "\n\n" . $caption, [
            'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
        ]);
        return;
    }

    botDeliverPhoto($client, $chatId, $imagePaths[0], $caption, [
        'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
    ]);
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
 * Legacy callback from older messages: do not dump albums into chat.
 * Open Mini App for that car instead (no left/right swipe to other cars).
 */
function sendAllCarPhotos(TelegramClient $client, int|string $chatId, int $carId): void
{
    $car = findCarById($carId);

    if ($car === null) {
        $client->sendMessage($chatId, notFoundMessage((string) $carId));
        return;
    }

    $vin = (string) $car['vin_code'];
    $url = miniAppCarUrl($vin, ['photos' => '1']);
    $keyboard = json_encode([
        'inline_keyboard' => [[
            [
                'text'    => 'Дидани ҳамаи суратҳо',
                'web_app' => ['url' => $url],
            ],
        ], [
            miniAppWebAppButton($carId, $vin),
        ]],
    ], JSON_UNESCAPED_UNICODE);

    botDeliverMessage(
        $client,
        $chatId,
        '📷 Суратҳои ин мошин дар Mini App кушода мешаванд (бе свайпи мошинҳои дигар).',
        ['reply_markup' => $keyboard]
    );
}
