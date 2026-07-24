<?php

declare(strict_types=1);

/**
 * Боркунии конфиги production аз беруни public_html (Hostinger).
 * Масир: /home/u417315406/domains/inovaauto.com/private/carsbot/
 */

function carsbotPrivateConfigDir(): ?string
{
    /** @var list<string|null> $candidates */
    $candidates = [];

    $envPath = getenv('CARSBOT_CONFIG_PATH');
    if (is_string($envPath) && $envPath !== '') {
        $candidates[] = rtrim(str_replace('\\', '/', $envPath), '/');
    }

    $candidates[] = '/home/u417315406/domains/inovaauto.com/private/carsbot';

    foreach ($candidates as $dir) {
        if ($dir !== null && $dir !== '' && is_dir($dir)) {
            return $dir;
        }
    }

    return null;
}

function carsbotLoadPrivateConfig(): void
{
    $dir = carsbotPrivateConfigDir();

    if ($dir === null) {
        return;
    }

    foreach (['database.php', 'app.local.php', 'telegram.local.php'] as $file) {
        $path = $dir . '/' . $file;

        if (is_file($path)) {
            require $path;
        }
    }
}

carsbotLoadPrivateConfig();
