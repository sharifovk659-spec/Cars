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

    if ($count === 1) {
        botDeliverPhoto($client, $chatId, $imagePaths[0], $caption, [
            'reply_markup' => $miniAppKeyboard,
        ]);
        return;
    }

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
        $client->sendPhoto($chatId, $paths[0], $caption);
        return;
    }

    $client->sendMediaGroup($chatId, $paths, $caption);
}
