<?php

declare(strict_types=1);

/**
 * Telegram Bot конфигуратсия
 * Token-и аслиро дар config/telegram.local.php нигоҳ доред
 */

define('TELEGRAM_MINI_APP_HOME', APP_URL . '/miniapp/index.html');
define('TELEGRAM_MINI_APP_URL', TELEGRAM_MINI_APP_HOME);

function miniAppHomeUrl(): string
{
    return TELEGRAM_MINI_APP_HOME;
}

function miniAppCarUrl(string $vin): string
{
    return APP_URL . '/miniapp/car.php?vin=' . urlencode(strtoupper(trim($vin)));
}

function miniAppMenuButtonText(): string
{
    return 'Открыть приложение';
}
