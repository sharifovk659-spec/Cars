<?php

declare(strict_types=1);

/**
 * Smoke checks for isolated car cards + "view all photos" callback.
 * Usage: php deploy/test_bot_message_layout.php
 */

$root = dirname(__DIR__);
require_once $root . '/bot/helpers.php';
require_once $root . '/bot/handlers.php';

$failed = 0;

function assertTrue(bool $ok, string $label): void
{
    global $failed;
    if ($ok) {
        echo "[ok] {$label}\n";
        return;
    }
    $failed++;
    echo "[FAIL] {$label}\n";
}

$src = file_get_contents($root . '/bot/handlers.php') ?: '';
$srcHelpers = file_get_contents($root . '/bot/helpers.php') ?: '';
$srcClient = file_get_contents($root . '/bot/TelegramClient.php') ?: '';
$srcWebhook = file_get_contents($root . '/bot/webhook.php') ?: '';

assertTrue(!preg_match('/\$client->sendMediaGroup\s*\(/', $src), 'handlers no longer call sendMediaGroup');
assertTrue(str_contains($src, 'sendIsolatedImage'), 'car card uses isolated images');
assertTrue(str_contains($src, "callback_data' => 'photos:"), 'view-all uses photos callback');
assertTrue(str_contains($src, 'Открыть Mini App') || str_contains($src, 'miniAppWebAppButton'), 'mini app button present');
assertTrue(str_contains($srcWebhook, "photos:"), 'webhook handles photos callback');
assertTrue(str_contains($srcWebhook, 'sendAllCarPhotos'), 'webhook calls sendAllCarPhotos');
assertTrue(str_contains($srcHelpers, 'sendIsolatedImage'), 'botDeliverPhoto uses isolated images');
assertTrue(str_contains($srcClient, 'function sendIsolatedImage'), 'TelegramClient has sendIsolatedImage');
assertTrue(str_contains($src, 'sendCarsToChat'), 'multi-car vertical sender exists');
assertTrue(str_contains($srcWebhook, 'findCarsBySearchQuery'), 'webhook searches multiple cars');
assertTrue(function_exists('findCarsBySearchQuery'), 'findCarsBySearchQuery defined');
assertTrue(function_exists('findCarBySearchQuery'), 'findCarBySearchQuery still available');
assertTrue(findCarsBySearchQuery('', 5) === [], 'empty query returns no cars');

echo $failed === 0 ? "ALL_PASSED\n" : "FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
