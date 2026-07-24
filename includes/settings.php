<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

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
    if (defined('TELEGRAM_BOT_TOKEN') && TELEGRAM_BOT_TOKEN !== '') {
        return TELEGRAM_BOT_TOKEN;
    }

    $localFile = __DIR__ . '/../config/telegram.local.php';

    if (is_file($localFile)) {
        $token = include $localFile;
        if (is_string($token) && $token !== '') {
            return $token;
        }
    }

    return getSetting('telegram_bot_token', '') ?? '';
}

function getMaxCarImages(): int
{
    $value = (int) (getSetting('max_car_images', (string) MAX_CAR_IMAGES) ?? MAX_CAR_IMAGES);

    return max(1, min(5, $value));
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

function resolveImageFullPath(?string $relativePath): ?string
{
    if ($relativePath === null || $relativePath === '') {
        return null;
    }

    $relative = str_replace('\\', '/', ltrim($relativePath, '/\\'));

    if (str_contains($relative, '..') || !str_starts_with($relative, 'uploads/')) {
        return null;
    }

    $fullPath = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $realPath = realpath($fullPath);
    $uploadsRoot = realpath(APP_ROOT . '/uploads');

    if ($realPath === false || $uploadsRoot === false || !is_file($realPath)) {
        return null;
    }

    if (!str_starts_with($realPath, $uploadsRoot)) {
        return null;
    }

    return $realPath;
}

function resolveImagePublicUrl(?string $relativePath): ?string
{
    if ($relativePath === null || $relativePath === '') {
        return null;
    }

    $relative = str_replace('\\', '/', ltrim($relativePath, '/\\'));

    if (str_contains($relative, '..') || !str_starts_with($relative, 'uploads/')) {
        return null;
    }

    return rtrim(APP_URL, '/') . '/' . $relative;
}
