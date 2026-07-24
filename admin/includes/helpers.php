<?php

declare(strict_types=1);

if (defined('TG_CARS_ADMIN_HELPERS')) {
    return;
}
define('TG_CARS_ADMIN_HELPERS', true);

require_once __DIR__ . '/../../includes/car_common.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function adminUrl(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/admin/' . ltrim($path, '/');
}

function formatDateTime(?string $datetime, string $fallback = '—'): string
{
    if ($datetime === null || $datetime === '') {
        return $fallback;
    }

    $timestamp = strtotime($datetime);

    return $timestamp ? date('d.m.Y H:i', $timestamp) : $fallback;
}

function carStatusClass(string $status): string
{
    return 'status-' . $status;
}

function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function logActivity(int $adminId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO activity_logs (admin_id, action, entity_type, entity_id, details, ip_address)
         VALUES (:admin_id, :action, :entity_type, :entity_id, :details, :ip_address)'
    );

    $stmt->execute([
        'admin_id'    => $adminId,
        'action'      => $action,
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'details'     => $details,
        'ip_address'  => clientIp(),
    ]);
}

function carImageUrl(?string $path): ?string
{
    if ($path === null || $path === '') {
        return null;
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    require_once __DIR__ . '/../../includes/settings.php';

    return resolveImagePublicUrl($path);
}

function flashSet(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** @return array{type: string, message: string}|null */
function flashGet(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}
