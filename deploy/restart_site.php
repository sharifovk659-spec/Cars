<?php

declare(strict_types=1);

/**
 * Restart / warm site for speed (CLI):
 *   php deploy/restart_site.php
 *
 * - resets OPcache
 * - restarts bot chat cleanup daemon
 * - warms critical PHP entrypoints
 */

$root = dirname(__DIR__);
require_once $root . '/config/app.php';

echo "Restarting site...\n";

if (function_exists('opcache_reset')) {
    $ok = @opcache_reset();
    echo 'OPcache reset: ' . ($ok ? 'ok' : 'failed') . PHP_EOL;
} else {
    echo "OPcache: not available\n";
}

$daemonScript = $root . '/deploy/ensure_chat_cleanup_daemon.sh';
if (is_file($daemonScript)) {
    $cmd = 'bash ' . escapeshellarg($daemonScript) . ' 2>&1';
    echo shell_exec($cmd) ?: "daemon script ran\n";
}

$warm = [
    $root . '/config/app.php',
    $root . '/config/database.php',
    $root . '/includes/settings.php',
    $root . '/includes/image_paths.php',
    $root . '/bot/helpers.php',
    $root . '/bot/handlers.php',
];

foreach ($warm as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

echo "Warm files loaded\n";
echo "Done.\n";
