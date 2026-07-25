<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/car_retention.php';

$result = purgeExpiredCars(db());

echo json_encode([
    'ok'              => true,
    'retention_months'=> carRetentionMonths(),
    'cutoff_date'     => carRetentionCutoffDate(),
    'deleted'         => $result['deleted'],
    'vins'            => $result['vins'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
