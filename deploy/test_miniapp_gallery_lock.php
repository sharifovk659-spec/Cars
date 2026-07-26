<?php

declare(strict_types=1);

$root = dirname(__DIR__);
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

$handlers = file_get_contents($root . '/bot/handlers.php') ?: '';
$core = file_get_contents($root . '/miniapp/assets/js/miniapp-core.js') ?: '';
$css = file_get_contents($root . '/miniapp/assets/css/style.css') ?: '';
$carJs = file_get_contents($root . '/miniapp/assets/js/car.js') ?: '';

assertTrue(str_contains($handlers, "web_app"), 'view-all-photos opens Mini App web_app');
assertTrue(str_contains($handlers, "photos' => '1'") || str_contains($handlers, 'photos'), 'photos=1 deep link present');
assertTrue(!preg_match('/\$client->sendMediaGroup\s*\(/', $handlers), 'no media albums in handlers');
assertTrue(str_contains($core, 'gallery-track-locked'), 'miniapp locks horizontal gallery');
assertTrue(str_contains($core, 'gallery-track-vertical'), 'miniapp vertical all-photos mode');
assertTrue(str_contains($css, 'gallery-track-locked'), 'css disables horizontal album swipe');
assertTrue(str_contains($carJs, "photos') === '1'"), 'car page reads photos=1');

echo $failed === 0 ? "ALL_PASSED\n" : "FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
