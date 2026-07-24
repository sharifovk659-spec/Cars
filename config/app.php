<?php

declare(strict_types=1);

/**
 * Telegram Cars — конфигуратсияи умумии барнома
 */

define('APP_ROOT', dirname(__DIR__));

require_once __DIR__ . '/bootstrap.php';

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Telegram Cars');
}

if (!defined('APP_URL')) {
    define('APP_URL', 'http://localhost/Telegram-cars');
}

define('UPLOADS_PATH', APP_ROOT . '/uploads/cars');
define('UPLOADS_URL', APP_URL . '/uploads/cars');

define('MAX_CAR_IMAGES', 5);
define('MIN_CAR_IMAGES', 1);
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);

/** @var list<string> */
define('ALLOWED_IMAGE_MIMES', ['image/jpeg', 'image/png', 'image/webp']);

/** @var list<string> */
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

define('TIMEZONE', 'Asia/Dushanbe');
date_default_timezone_set(TIMEZONE);
