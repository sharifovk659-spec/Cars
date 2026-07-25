<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../bot/helpers.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

$query = strtoupper(trim($_GET['q'] ?? ''));

if ($query === '') {
    echo json_encode(['found' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$car = findCarBySearchQuery($query);

if ($car === null) {
    echo json_encode(['found' => false, 'query' => $query], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'found' => true,
    'query' => $query,
    'car'   => carLookupPayload($car),
], JSON_UNESCAPED_UNICODE);
