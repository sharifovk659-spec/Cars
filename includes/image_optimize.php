<?php

declare(strict_types=1);

require_once __DIR__ . '/settings.php';

/**
 * Resize/compress car photos for faster Mini App loading.
 */

function carImageCacheDir(): string
{
    $dir = APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

/**
 * Compress and downscale an uploaded image file in place.
 */
function optimizeStoredCarImage(string $fullPath, int $maxWidth = 1280, int $jpegQuality = 82): void
{
    if (!is_file($fullPath) || !function_exists('imagecreatetruecolor')) {
        return;
    }

    $info = @getimagesize($fullPath);
    if ($info === false) {
        return;
    }

    [$width, $height] = $info;
    $mime = $info['mime'] ?? '';

    if ($width <= 0 || $height <= 0) {
        return;
    }

    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($fullPath),
        'image/png'  => @imagecreatefrompng($fullPath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($fullPath) : false,
        default      => false,
    };

    if ($source === false) {
        return;
    }

    $targetWidth = $width;
    $targetHeight = $height;

    if ($width > $maxWidth) {
        $targetWidth = $maxWidth;
        $targetHeight = (int) max(1, round($height * ($maxWidth / $width)));
    }

    $needsResize = $targetWidth !== $width || $targetHeight !== $height;
    $canvas = $needsResize ? imagecreatetruecolor($targetWidth, $targetHeight) : $source;

    if ($canvas === false) {
        imagedestroy($source);
        return;
    }

    if ($needsResize) {
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);
    }

    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($canvas, $fullPath, $jpegQuality),
        'image/png'  => imagepng($canvas, $fullPath, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, $fullPath, $jpegQuality) : false,
        default      => false,
    };

    imagedestroy($canvas);
}

/**
 * Public URL for a mobile-optimized image (cached resize).
 */
function resolveImageMobileUrl(?string $relativePath, int $width = 720): ?string
{
    $original = resolveImagePublicUrl($relativePath);
    if ($original === null || $relativePath === null || $relativePath === '') {
        return null;
    }

    $relative = str_replace('\\', '/', ltrim($relativePath, '/\\'));
    if (!str_starts_with($relative, 'uploads/cars/')) {
        return $original;
    }

    return rtrim(APP_URL, '/') . '/api/img.php?p=' . rawurlencode($relative) . '&w=' . max(200, min(1280, $width));
}

/**
 * Build or reuse a cached resized image and return absolute path.
 */
function buildCachedCarImage(string $relativePath, int $width = 720): ?string
{
    $realPath = resolveImageFullPath($relativePath);
    if ($realPath === null) {
        return null;
    }

    $width = max(200, min(1280, $width));
    $mtime = (int) filemtime($realPath);
    $hash = md5($relativePath . '|' . $width . '|' . $mtime);
    $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return $realPath;
    }

    $cacheName = $hash . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $cachePath = carImageCacheDir() . DIRECTORY_SEPARATOR . $cacheName;

    if (is_file($cachePath) && filemtime($cachePath) >= $mtime) {
        return $cachePath;
    }

    if (!function_exists('imagecreatetruecolor')) {
        return $realPath;
    }

    $info = @getimagesize($realPath);
    if ($info === false) {
        return $realPath;
    }

    [$srcW, $srcH] = $info;
    $mime = $info['mime'] ?? '';

    if ($srcW <= $width) {
        return $realPath;
    }

    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($realPath),
        'image/png'  => @imagecreatefrompng($realPath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($realPath) : false,
        default      => false,
    };

    if ($source === false) {
        return $realPath;
    }

    $dstW = $width;
    $dstH = (int) max(1, round($srcH * ($width / $srcW)));
    $canvas = imagecreatetruecolor($dstW, $dstH);

    if ($canvas === false) {
        imagedestroy($source);
        return $realPath;
    }

    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $dstW, $dstH, $transparent);
    }

    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($source);

    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($canvas, $cachePath, 80),
        'image/png'  => imagepng($canvas, $cachePath, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, $cachePath, 80) : false,
        default      => false,
    };

    imagedestroy($canvas);

    return $ok ? $cachePath : $realPath;
}
