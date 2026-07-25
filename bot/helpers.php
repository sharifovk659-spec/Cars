<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/telegram.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/car_common.php';

/**
 * @return array<string, mixed>|null
 */
function findCarById(int $id): ?array
{
    $stmt = db()->prepare(
        "SELECT c.*
         FROM cars c
         WHERE c.id = :id AND c.deleted_at IS NULL
         LIMIT 1"
    );
    $stmt->execute(['id' => $id]);
    $car = $stmt->fetch();

    return $car ?: null;
}

/**
 * @return array<string, mixed>|null
 */
function findCarBySearchQuery(string $query): ?array
{
    $query = strtoupper(trim($query));
    $pdo = db();

    if ($query === '') {
        return null;
    }

    if (preg_match('/^\d{4}$/', $query)) {
        $stmt = $pdo->prepare(
            "SELECT c.*
             FROM cars c
             WHERE c.deleted_at IS NULL AND RIGHT(c.vin_code, 4) = :digits
             ORDER BY c.created_at DESC
             LIMIT 1"
        );
        $stmt->execute(['digits' => $query]);
        $car = $stmt->fetch();

        return $car ?: null;
    }

    if (preg_match('/^\d{5}$/', $query)) {
        $stmt = $pdo->prepare(
            "SELECT c.*
             FROM cars c
             WHERE c.deleted_at IS NULL AND RIGHT(c.vin_code, 5) = :digits
             ORDER BY c.created_at DESC
             LIMIT 1"
        );
        $stmt->execute(['digits' => $query]);
        $car = $stmt->fetch();

        return $car ?: null;
    }

    $stmt = $pdo->prepare(
        "SELECT c.*
         FROM cars c
         WHERE c.deleted_at IS NULL AND c.vin_code = :vin
         LIMIT 1"
    );
    $stmt->execute(['vin' => $query]);
    $car = $stmt->fetch();

    return $car ?: null;
}

/** @return list<array{id: int, image_path: string, sort_order: int, url: string|null}> */
function getCarImagesList(int $carId): array
{
    $stmt = db()->prepare(
        'SELECT id, image_path, sort_order FROM car_images WHERE car_id = :car_id ORDER BY sort_order ASC'
    );
    $stmt->execute(['car_id' => $carId]);
    $images = [];

    foreach ($stmt->fetchAll() as $row) {
        $images[] = [
            'id'         => (int) $row['id'],
            'image_path' => $row['image_path'],
            'sort_order' => (int) $row['sort_order'],
            'url'        => resolveImagePublicUrl($row['image_path']),
        ];
    }

    return $images;
}

/**
 * @param array<string, mixed> $car
 * @return array<string, mixed>
 */
function formatCarForApi(array $car): array
{
    $images = getCarImagesList((int) $car['id']);

    return [
        'id'            => (int) $car['id'],
        'vin_code'      => $car['vin_code'],
        'name'          => $car['name'],
        'description'   => $car['description'],
        'receive_date'  => $car['receive_date'],
        'upload_date'   => $car['upload_date'],
        'upload_number' => $car['upload_number'] ?? null,
        'vagon'         => $car['vagon'] ?? null,
        'treiler'       => $car['treiler'] ?? null,
        'status'        => $car['status'],
        'status_label'  => carStatusLabel((string) $car['status']),
        'contact_name'  => $car['contact_name'],
        'contact_phone' => $car['contact_phone'],
        'notes'         => $car['notes'],
        'upload_status_label' => carUploadStatusLabel($car),
        'upload_type_label'   => carUploadTypeLabel($car),
        'created_at'    => $car['created_at'],
        'labels'        => carFieldLabels(),
        'images'        => array_map(static function (array $img): array {
            return [
                'id'         => $img['id'],
                'sort_order' => $img['sort_order'],
                'url'        => $img['url'],
                'is_main'    => $img['sort_order'] === 1,
            ];
        }, $images),
    ];
}

function botUploadCaptionLabel(array $car): string
{
    return carUploadStatusLabel($car);
}

function buildCarCaption(array $car): string
{
    $name = htmlspecialchars((string) ($car['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $vin = htmlspecialchars((string) $car['vin_code'], ENT_QUOTES, 'UTF-8');
    $company = htmlspecialchars(getSetting('company_name', APP_NAME) ?: APP_NAME, ENT_QUOTES, 'UTF-8');
    $modelLabel = htmlspecialchars(carFieldLabel('name'), ENT_QUOTES, 'UTF-8');
    $sharjaLabel = htmlspecialchars(carFieldLabel('receive_date'), ENT_QUOTES, 'UTF-8');

    $lines = [
        '🚘 <b>' . $modelLabel . ':</b> ' . $name,
        '<i>' . $company . '</i>',
        '━━━━━━━━━━━━━━',
        '🆔 <b>VIN:</b> <code>' . $vin . '</code>',
    ];

    if (!empty($car['receive_date'])) {
        $lines[] = '📍 <b>' . $sharjaLabel . ':</b> ' . formatDate($car['receive_date']);
    }

    $uploadType = carUploadTypeLabel($car);
    if ($uploadType === 'Вагон' || $uploadType === 'Трейлер') {
        $lines[] = '⬆️ <b>Боргири шуд дар:</b> <b>' . htmlspecialchars($uploadType, ENT_QUOTES, 'UTF-8') . '</b>';
    } elseif (!empty($car['upload_date'])) {
        $lines[] = '⬆️ <b>' . htmlspecialchars($uploadType, ENT_QUOTES, 'UTF-8') . '</b>';
    } else {
        $lines[] = '⬆️ Ҳоло боргирӣ нашудааст';
    }

    return implode("\n", $lines);
}

/** @return array<string, mixed> */
function miniAppKeyboard(int $carId, string $vin): array
{
    return [
        'inline_keyboard' => [[
            [
                'text'    => 'Открыть Mini App',
                'web_app' => ['url' => miniAppCarUrl($vin)],
            ],
        ]],
    ];
}

function miniAppWebAppButton(int $carId, string $vin): array
{
    return [
        'text'    => 'Открыть Mini App',
        'web_app' => ['url' => miniAppCarUrl($vin)],
    ];
}

/** @return array<string, mixed> */
function viewAllPhotosKeyboard(int $carId): array
{
    return [
        'inline_keyboard' => [[
            [
                'text'          => 'Дидани ҳамаи суратҳо',
                'callback_data' => 'photos:' . $carId,
            ],
        ]],
    ];
}

function welcomeMessage(string $firstName = ''): string
{
    $name = $firstName !== '' ? htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') : 'дӯст';
    $template = getSetting('welcome_message', '') ?? '';

    if ($template === '') {
        $botName = getSetting('bot_name', APP_NAME) ?: APP_NAME;

        return implode("\n", [
            "👋 <b>Хуш омадед, {$name}!</b>",
            '',
            "🔍 <b>{$botName}</b>",
            'Лутфан <b>VIN Code</b> ё <b>5 рақами охирин</b>-ро фиристед.',
        ]);
    }

    return replaceSettingPlaceholders($template, [
        'name'    => $name,
        'company' => htmlspecialchars(getSetting('company_name', APP_NAME) ?? APP_NAME, ENT_QUOTES, 'UTF-8'),
    ]);
}

function notFoundMessage(string $query): string
{
    $template = getSetting('not_found_message', '') ?? '';

    if ($template === '') {
        return implode("\n", [
            '❌ <b>Мошин ёфт нашуд</b>',
            '',
            'Дар бораи <code>' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '</code> маълумот нест.',
        ]);
    }

    return replaceSettingPlaceholders($template, [
        'query' => htmlspecialchars($query, ENT_QUOTES, 'UTF-8'),
    ]);
}

function noPhotoMessage(): string
{
    return '📷 Сурат ҳоло илода нашудааст';
}

/**
 * @param array<string, mixed> $from
 */
function upsertTelegramUser(array $from): int
{
    $telegramId = (int) ($from['id'] ?? 0);

    $stmt = db()->prepare('SELECT id FROM telegram_users WHERE telegram_id = :telegram_id LIMIT 1');
    $stmt->execute(['telegram_id' => $telegramId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $update = db()->prepare(
            'UPDATE telegram_users SET
                username = :username,
                first_name = :first_name,
                last_name = :last_name,
                language_code = :language_code,
                updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            'username'      => $from['username'] ?? null,
            'first_name'    => $from['first_name'] ?? null,
            'last_name'     => $from['last_name'] ?? null,
            'language_code' => $from['language_code'] ?? null,
            'id'            => $existing['id'],
        ]);

        return (int) $existing['id'];
    }

    $insert = db()->prepare(
        'INSERT INTO telegram_users (telegram_id, username, first_name, last_name, language_code)
         VALUES (:telegram_id, :username, :first_name, :last_name, :language_code)'
    );
    $insert->execute([
        'telegram_id'   => $telegramId,
        'username'      => $from['username'] ?? null,
        'first_name'    => $from['first_name'] ?? null,
        'last_name'     => $from['last_name'] ?? null,
        'language_code' => $from['language_code'] ?? null,
    ]);

    return (int) db()->lastInsertId();
}

function logTelegramSearch(int $userId, string $query, ?string $vinCode, int $resultsCount): void
{
    $stmt = db()->prepare(
        'INSERT INTO search_history (user_id, search_query, vin_code, results_count)
         VALUES (:user_id, :search_query, :vin_code, :results_count)'
    );
    $stmt->execute([
        'user_id'        => $userId,
        'search_query'   => $query,
        'vin_code'       => $vinCode,
        'results_count'  => $resultsCount,
    ]);
}

/**
 * @return list<string> full paths of existing images
 */
function getCarImagePaths(int $carId): array
{
    $paths = [];

    foreach (getCarImagesList($carId) as $image) {
        $full = resolveImageFullPath($image['image_path']);
        if ($full !== null) {
            $paths[] = $full;
        }
    }

    return $paths;
}
