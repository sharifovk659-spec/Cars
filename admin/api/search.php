<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/search.php';
require_once __DIR__ . '/../includes/ui.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

$query = trim($_GET['q'] ?? '');

if ($query === '') {
    echo json_encode(['query' => '', 'count' => 0, 'cars' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$cars = searchAdminCars(db(), $query);
$payload = array_map(static fn (array $car): array => adminSearchCarPayload($car), $cars);

echo json_encode([
    'query' => $query,
    'count' => count($payload),
    'cars'  => $payload,
], JSON_UNESCAPED_UNICODE);
