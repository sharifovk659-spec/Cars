<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/bot/helpers.php';

$queries = array_slice($argv, 1);

if ($queries === []) {
    $queries = ['76870', '12000', '120202'];
}

foreach ($queries as $query) {
    $car = findCarBySearchQuery($query);
    if ($car === null) {
        echo $query . " => NOT FOUND\n";
        continue;
    }

    echo $query . ' => VIN=' . $car['vin_code']
        . ' upload=' . ($car['upload_number'] ?? '')
        . ' name=' . ($car['name'] ?? '') . "\n";
}
