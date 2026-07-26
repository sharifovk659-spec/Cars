<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/image_optimize.php';

$path = trim((string) ($_GET['p'] ?? ''));
$width = (int) ($_GET['w'] ?? 720);

if ($path === '' || str_contains($path, '..') || !str_starts_with($path, 'uploads/cars/')) {
    http_response_code(404);
    exit;
}

$cached = buildCachedCarImage($path, $width);
if ($cached === null || !is_file($cached)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($cached, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    default => 'image/jpeg',
};

$mtime = filemtime($cached) ?: time();
$size = filesize($cached) ?: 0;
$etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=604800, immutable');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

if (
    (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag)
    || (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime)
) {
    http_response_code(304);
    exit;
}

header('Content-Length: ' . (string) $size);
readfile($cached);
