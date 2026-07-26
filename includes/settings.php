<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/image_paths.php';

/** @var array<string, string|null>|null */
$settingsCache = null;

function settingsLoadAll(): array
{
    global $settingsCache;

    if ($settingsCache !== null) {
        return $settingsCache;
    }

    $settingsCache = [];

    try {
        $stmt = db()->query('SELECT setting_key, setting_value FROM settings');
        foreach ($stmt->fetchAll() as $row) {
            $settingsCache[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable) {
        $settingsCache = [];
    }

    return $settingsCache;
}

function getSetting(string $key, ?string $default = null): ?string
{
    $all = settingsLoadAll();

    return array_key_exists($key, $all) ? ($all[$key] ?? $default) : $default;
}

function setSetting(string $key, ?string $value): void
{
    global $settingsCache;

    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value)
         VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE setting_value = :value_update, updated_at = NOW()'
    );
    $stmt->execute([
        'key'          => $key,
        'value'        => $value,
        'value_update' => $value,
    ]);

    $settingsCache = null;
}

function getBotToken(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (defined('TELEGRAM_BOT_TOKEN') && TELEGRAM_BOT_TOKEN !== '') {
        $cached = TELEGRAM_BOT_TOKEN;
        return $cached;
    }

    $localFile = __DIR__ . '/../config/telegram.local.php';

    if (is_file($localFile)) {
        $token = include $localFile;
        if (is_string($token) && $token !== '') {
            $cached = $token;
            return $cached;
        }
    }

    $cached = getSetting('telegram_bot_token', '') ?? '';
    return $cached;
}

function getMaxCarImages(): int
{
    $value = (int) (getSetting('max_car_images', (string) MAX_CAR_IMAGES) ?? MAX_CAR_IMAGES);
    $cap = defined('MAX_CAR_IMAGES_CAP') ? (int) MAX_CAR_IMAGES_CAP : 2000;

    return max(1, min($cap, $value));
}

function maskToken(string $token): string
{
    if ($token === '') {
        return '—';
    }

    if (strlen($token) <= 10) {
        return str_repeat('•', strlen($token));
    }

    return substr($token, 0, 6) . str_repeat('•', max(4, strlen($token) - 10)) . substr($token, -4);
}

function replaceSettingPlaceholders(string $template, array $vars): string
{
    foreach ($vars as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }

    return $template;
}

