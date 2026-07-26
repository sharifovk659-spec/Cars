<?php

declare(strict_types=1);

/**
 * Lightweight image path helpers (no DB).
 */

function resolveImageFullPath(?string $relativePath): ?string
{
    if ($relativePath === null || $relativePath === '') {
        return null;
    }

    $relative = str_replace('\\', '/', ltrim($relativePath, '/\\'));

    if (str_contains($relative, '..') || !str_starts_with($relative, 'uploads/')) {
        return null;
    }

    static $uploadsRoot = null;
    if ($uploadsRoot === false) {
        return null;
    }
    if ($uploadsRoot === null) {
        $resolved = realpath(APP_ROOT . '/uploads');
        $uploadsRoot = $resolved !== false ? $resolved : false;
        if ($uploadsRoot === false) {
            return null;
        }
    }

    $fullPath = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $realPath = realpath($fullPath);

    if ($realPath === false || !is_file($realPath)) {
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
