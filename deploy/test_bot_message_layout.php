<?php

declare(strict_types=1);

/**
 * Smoke checks for bot "one car = one message" behavior (no albums for car cards).
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
assertTrue(!preg_match('/\$client->sendMediaGroup\s*\(/', $src), 'handlers no longer call sendMediaGroup');
assertTrue(str_contains($src, 'sendCarsToChat'), 'multi-car vertical sender exists');
assertTrue(str_contains($src, 'photos:'), 'view-all-photos callback still bound to car id');

$srcWebhook = file_get_contents($root . '/bot/webhook.php') ?: '';
assertTrue(str_contains($srcWebhook, 'findCarsBySearchQuery'), 'webhook searches multiple cars');
assertTrue(str_contains($srcWebhook, 'sendCarsToChat'), 'webhook sends cars as separate messages');

assertTrue(function_exists('findCarsBySearchQuery'), 'findCarsBySearchQuery defined');
assertTrue(function_exists('findCarBySearchQuery'), 'findCarBySearchQuery still available');

$empty = findCarsBySearchQuery('', 5);
assertTrue($empty === [], 'empty query returns no cars');

echo $failed === 0 ? "ALL_PASSED\n" : "FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
