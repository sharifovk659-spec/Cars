<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/telegram.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/car_common.php';
require_once __DIR__ . '/../includes/image_optimize.php';

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
function findCarRow(PDO $pdo, string $sql, array $params): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $car = $stmt->fetch();

    return $car ?: null;
}

/**
 * @return array<string, mixed>|null
 */
/**
 * @return list<array<string, mixed>>
 */
function findCarsBySearchQuery(string $query, int $limit = 8): array
{
    $query = strtoupper(trim($query));
    $limit = max(1, min(15, $limit));

    if ($query === '') {
        return [];
    }

    $pdo = db();
    $cars = [];
    $seen = [];

    $appendRows = static function (array $rows) use (&$cars, &$seen, $limit): void {
        foreach ($rows as $row) {
            if (count($cars) >= $limit) {
                return;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $cars[] = $row;
        }
    };

    $exactVin = findCarRow(
        $pdo,
        "SELECT c.*
         FROM cars c
         WHERE c.deleted_at IS NULL AND c.vin_code = :query
         ORDER BY c.created_at DESC
         LIMIT 1",
        ['query' => $query]
    );
    if ($exactVin !== null) {
        $appendRows([$exactVin]);
    }

    if (count($cars) < $limit) {
        $exactUpload = findCarRow(
            $pdo,
            "SELECT c.*
             FROM cars c
             WHERE c.deleted_at IS NULL AND UPPER(TRIM(c.upload_number)) = :query
             ORDER BY c.created_at DESC
             LIMIT 1",
            ['query' => $query]
        );
        if ($exactUpload !== null) {
            $appendRows([$exactUpload]);
        }
    }

    if (preg_match('/^\d{4,5}$/', $query) && count($cars) < $limit) {
        $length = strlen($query);
        $remaining = $limit - count($cars);

        $stmt = $pdo->prepare(
            "SELECT c.*
             FROM cars c
             WHERE c.deleted_at IS NULL AND RIGHT(c.vin_code, :length) = :digits
             ORDER BY c.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->bindValue(':digits', $query);
        $stmt->bindValue(':limit', $remaining, PDO::PARAM_INT);
        $stmt->execute();
        $appendRows($stmt->fetchAll());

        if (count($cars) < $limit) {
            $remaining = $limit - count($cars);
            $stmt = $pdo->prepare(
                "SELECT c.*
                 FROM cars c
                 WHERE c.deleted_at IS NULL
                   AND c.upload_number IS NOT NULL
                   AND TRIM(c.upload_number) <> ''
                   AND RIGHT(TRIM(c.upload_number), :length) = :digits
                 ORDER BY c.created_at DESC
                 LIMIT :limit"
            );
            $stmt->bindValue(':length', $length, PDO::PARAM_INT);
            $stmt->bindValue(':digits', $query);
            $stmt->bindValue(':limit', $remaining, PDO::PARAM_INT);
            $stmt->execute();
            $appendRows($stmt->fetchAll());
        }
    }

    if (preg_match('/^\d{6,}$/', $query) && count($cars) < $limit) {
        $remaining = $limit - count($cars);
        $stmt = $pdo->prepare(
            "SELECT c.*
             FROM cars c
             WHERE c.deleted_at IS NULL
               AND c.upload_number IS NOT NULL
               AND TRIM(c.upload_number) <> ''
               AND UPPER(TRIM(c.upload_number)) LIKE :query
             ORDER BY c.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':query', '%' . $query . '%');
        $stmt->bindValue(':limit', $remaining, PDO::PARAM_INT);
        $stmt->execute();
        $appendRows($stmt->fetchAll());
    }

    return $cars;
}

function findCarBySearchQuery(string $query): ?array
{
    $cars = findCarsBySearchQuery($query, 1);

    return $cars[0] ?? null;
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
        'receive_location' => $car['receive_location'] ?? 'sharjah',
        'receive_location_label' => carReceiveLocationLabel($car['receive_location'] ?? null),
        'receive_display' => carReceiveDisplayText($car),
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
        'upload_display'      => carUploadDisplayParts($car),
        'created_at'    => $car['created_at'],
        'labels'        => carFieldLabels(),
        'images'        => array_map(static function (array $img): array {
            return [
                'id'         => $img['id'],
                'sort_order' => $img['sort_order'],
                'url'        => resolveImageMobileUrl($img['image_path'], 540) ?? $img['url'],
                'url_full'   => $img['url'],
                'is_main'    => $img['sort_order'] === 1,
            ];
        }, $images),
    ];
}

function botDeliverMessage(TelegramClient $client, int|string $chatId, string $text, array $options = []): bool
{
    if ($client->sendMessage($chatId, $text, $options) !== null) {
        return true;
    }

    $fallback = $options;
    unset($fallback['parse_mode'], $fallback['reply_markup']);

    if ($client->sendMessage($chatId, strip_tags($text), $fallback) !== null) {
        return true;
    }

    error_log('Telegram bot failed to deliver message to chat ' . $chatId);

    return false;
}

/**
 * @param array<string, mixed> $options
 */
function botDeliverPhoto(
    TelegramClient $client,
    int|string $chatId,
    string $photoPath,
    string $caption,
    array $options = []
): bool {
    if ($client->sendPhoto($chatId, $photoPath, $caption, $options) !== null) {
        return true;
    }

    $fallback = $options;
    unset($fallback['reply_markup']);

    if ($client->sendPhoto($chatId, $photoPath, strip_tags($caption), $fallback) !== null) {
        return true;
    }

    return botDeliverMessage($client, $chatId, $caption, $options);
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
    $receiveText = htmlspecialchars(carReceiveDisplayText($car), ENT_QUOTES, 'UTF-8');

    $lines = [
        '🚘 <b>' . $modelLabel . ':</b> ' . $name,
        '<i>' . $company . '</i>',
        '━━━━━━━━━━━━━━',
        '🆔 <b>VIN:</b> <code>' . $vin . '</code>',
    ];

    if ($receiveText !== '') {
        $lines[] = '📍 ' . $receiveText;
    }

    $uploadType = carUploadTypeLabel($car);
    if (str_starts_with($uploadType, 'Вагон') || str_starts_with($uploadType, 'Трейлер') || !empty($car['upload_date'])) {
        $lines[] = buildBotUploadCaptionLine($car);
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
function viewAllPhotosKeyboard(int $carId, string $vin = ''): array
{
    if ($vin === '') {
        $car = findCarById($carId);
        $vin = (string) ($car['vin_code'] ?? '');
    }

    return [
        'inline_keyboard' => [[
            [
                'text'    => 'Дидани ҳамаи суратҳо',
                'web_app' => ['url' => miniAppCarUrl($vin, ['photos' => '1'])],
            ],
        ]],
    ];
}

function welcomeMessage(string $firstName = ''): string
{
    $name = $firstName !== '' ? htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') : 'друг';
    $template = trim((string) (getSetting('welcome_message', '') ?? ''));

    // Keep Russian start text; ignore legacy Tajik templates still stored in settings.
    $isLegacyTajik = $template !== '' && (
        str_contains($template, 'Хуш омадед')
        || str_contains($template, 'рақами боргири')
        || str_contains($template, '5 рақами')
    );

    if ($template !== '' && !$isLegacyTajik) {
        return replaceSettingPlaceholders($template, [
            'name'    => $name,
            'company' => htmlspecialchars(getSetting('company_name', APP_NAME) ?? APP_NAME, ENT_QUOTES, 'UTF-8'),
        ]);
    }

    return implode("\n", [
        "👋 <b>Добро пожаловать, {$name}!</b>",
        '',
        'Введите 4 последние символа vinCode машины',
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
