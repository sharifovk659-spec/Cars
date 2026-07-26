<?php

declare(strict_types=1);

/**
 * CLI smoke tests for typed admin search + cars filters helpers.
 * Usage: php deploy/test_typed_search.php
 */

$root = dirname(__DIR__);
require_once $root . '/config/database.php';
require_once $root . '/admin/includes/search.php';

$pdo = db();
$failed = 0;

function assertTrue(bool $cond, string $label): void
{
    global $failed;
    if ($cond) {
        echo "[ok] {$label}\n";
        return;
    }
    $failed++;
    echo "[FAIL] {$label}\n";
}

$empty = prepareAdminSearchQuery('vin', '');
assertTrue($empty['ok'] === false && $empty['error'] === 'empty', 'empty query rejected');

$short = prepareAdminSearchQuery('model', 'a');
assertTrue($short['ok'] === false && $short['error'] === 'short', 'short model rejected');

$digitsBad = prepareAdminSearchQuery('digits', 'ab12');
assertTrue($digitsBad['ok'] === false && $digitsBad['error'] === 'digits_short', 'short digits rejected');
$digits = prepareAdminSearchQuery('digits', '12ab34');
assertTrue($digits['ok'] === true && $digits['query'] === '1234', 'digits keep numbers only');

$vin = prepareAdminSearchQuery('vin', ' abc123 ');
assertTrue($vin['ok'] === true && $vin['query'] === 'ABC123', 'vin uppercased');

$phone = prepareAdminSearchQuery('phone', '+971 (50) 123-45');
assertTrue($phone['ok'] === true && $phone['query'] === '+9715012345', 'phone normalized');

$row = $pdo->query("SELECT vin_code, name, contact_phone FROM cars WHERE deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $vinCode = (string) $row['vin_code'];
    $byVin = searchAdminCars($pdo, $vinCode, 5, 'vin');
    assertTrue($byVin !== [], 'search by full VIN returns rows');

    if (strlen($vinCode) >= 4) {
        $suffix = substr($vinCode, -4);
        $byDigits = searchAdminCars($pdo, $suffix, 5, 'digits');
        assertTrue($byDigits !== [], 'search by last digits returns rows');
    }

    $name = trim((string) ($row['name'] ?? ''));
    if (mb_strlen($name) >= 2) {
        $byModel = searchAdminCars($pdo, mb_substr($name, 0, 2), 5, 'model');
        assertTrue(is_array($byModel), 'search by model runs');
    }

    $missing = searchAdminCars($pdo, 'ZZZNOCAR999999', 5, 'vin');
    assertTrue($missing === [], 'missing VIN returns empty');
} else {
    echo "[skip] no cars in DB for live search checks\n";
}

echo $failed === 0 ? "ALL_PASSED\n" : "FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
